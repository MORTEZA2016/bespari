<?php
/**
 * OrderSync — ارسال خودکار سفارش‌ها به سایت مادر (Push).
 *
 * @package BespariAgent
 */

namespace BespariAgent;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderSync {

	/**
	 * ثبت هوک‌ها.
	 */
	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );
		add_action( 'bespari_agent_orders_cron', array( __CLASS__, 'run' ) );

		// ووکامرس اختیاری: سفارش پرداخت‌شده جدید → صف.
		if ( function_exists( 'WC' ) ) {
			add_action( 'woocommerce_thankyou', array( __CLASS__, 'queue_wc_order' ), 10, 1 );
		}
	}

	/**
	 * بازه ۵ دقیقه.
	 */
	public static function add_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['bespari_agent_5min'] ) ) {
			$schedules['bespari_agent_5min'] = array(
				'interval' => 300,
				'display'  => __( 'هر ۵ دقیقه (بسپاری Agent)', 'bespari-agent' ),
			);
		}
		return $schedules;
	}

	/**
	 * صف سفارش ووکامرس پرداخت‌شده.
	 */
	public static function queue_wc_order( int $order_id ): void {
		if ( ! Http::is_ready() ) {
			return;
		}

		$queue = get_option( 'bespari_agent_queue', array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}

		// جلوگیری از دوبل در صف.
		foreach ( $queue as $item ) {
			if ( (int) $item['wc_order_id'] === $order_id ) {
				return;
			}
		}

		$queue[] = array(
			'wc_order_id' => $order_id,
			'attempts'    => 0,
			'queued_at'   => current_time( 'mysql' ),
		);

		update_option( 'bespari_agent_queue', $queue );
	}

	/**
	 * اجرای cron — ارسال سفارش‌های صف به سایت مادر.
	 */
	public static function run(): void {
		if ( ! Http::is_ready() ) {
			return;
		}

		$queue = get_option( 'bespari_agent_queue', array() );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			return;
		}

		$remaining = array();

		foreach ( $queue as $item ) {
			$result = self::push_order( (int) $item['wc_order_id'] );

			if ( $result['ok'] ) {
				Http::log( 'out', 'orders', 'ok', 'wc order #' . $item['wc_order_id'] . ' → ' . (string) ( $result['data']['order_number'] ?? '' ) );
				continue; // از صف حذف.
			}

			$item['attempts'] = (int) ( $item['attempts'] ?? 0 ) + 1;
			if ( $item['attempts'] >= 5 ) {
				Http::log( 'out', 'orders', 'failed', 'wc order #' . $item['wc_order_id'] . ' — حداکثر تلاش: ' . $result['error'] );
				continue; // رهاشده (failed).
			}

			$remaining[] = $item;
		}

		update_option( 'bespari_agent_queue', $remaining );
	}

	/**
	 * ارسال یک سفارش ووکامرس به سایت مادر.
	 *
	 * @return array{ok: bool, data: array, error: string}
	 */
	public static function push_order( int $wc_order_id ): array {
		$wc_order = function_exists( 'wc_get_order' ) ? wc_get_order( $wc_order_id ) : null;

		if ( ! $wc_order ) {
			// بدون ووکامرس یا سفارش ناموجود — صف رها شود.
			return array( 'ok' => true, 'data' => array(), 'error' => '' );
		}

		// Idempotency: external_ref = local site url hash + wc order id.
		$external_ref = 'wc-' . md5( home_url() ) . '-' . $wc_order_id;

		$items = array();
		foreach ( $wc_order->get_items() as $line_item ) {
			$product = $line_item->get_product();
			if ( ! $product ) {
				continue;
			}
			$items[] = array(
				'sku'      => $product->get_sku( 'edit' ),
				'quantity' => max( 1, (int) $line_item->get_quantity() ),
			);
		}

		if ( empty( $items ) ) {
			return array( 'ok' => true, 'data' => array(), 'error' => '' );
		}

		$body = array(
			'external_ref' => $external_ref,
			'customer'     => array(
				'name'    => $wc_order->get_formatted_billing_full_name(),
				'phone'   => $wc_order->get_billing_phone(),
				'address' => $wc_order->get_formatted_billing_address(),
			),
			'items'        => $items,
			'notes'        => 'بسپاری Agent — سفارش محلی #' . $wc_order_id,
		);

		return Http::signed_request( 'POST', 'bespari/v1/agent/orders', $body );
	}
}
