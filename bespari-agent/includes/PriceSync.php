<?php
/**
 * PriceSync — دریافت قیمت/موجودی از سایت مادر (Pull).
 *
 * @package BespariAgent
 */

namespace BespariAgent;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PriceSync {

	/**
	 * ثبت هوک‌ها.
	 */
	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );
		add_action( 'bespari_agent_prices_cron', array( __CLASS__, 'run' ) );
	}

	/**
	 * بازه ۳۰ دقیقه.
	 */
	public static function add_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['bespari_agent_30min'] ) ) {
			$schedules['bespari_agent_30min'] = array(
				'interval' => 1800,
				'display'  => __( 'هر ۳۰ دقیقه (بسپاری Agent)', 'bespari-agent' ),
			);
		}
		return $schedules;
	}

	/**
	 * اجرای cron — pull قیمت/موجودی و آپدیت ووکامرس (در صورت فعال بودن).
	 */
	public static function run(): void {
		if ( ! Http::is_ready() ) {
			return;
		}

		$since = get_option( 'bespari_agent_last_sync', '' );
		$path  = 'bespari/v1/agent/products';
		if ( '' !== $since ) {
			$path .= '?since=' . rawurlencode( $since );
		}

		$result = Http::signed_request( 'GET', $path );

		if ( ! $result['ok'] ) {
			Http::log( 'in', 'products', 'error', $result['error'] );
			return;
		}

		$products = $result['data']['products'] ?? array();
		$updated  = 0;

		if ( is_array( $products ) && function_exists( 'wc_get_product_id_by_sku' ) ) {
			foreach ( $products as $p ) {
				$updated += self::apply_to_wc( $p );
			}
		}

		update_option( 'bespari_agent_last_sync', (string) ( $result['data']['server_time'] ?? current_time( 'mysql' ) ) );
		Http::log( 'in', 'products', 'ok', count( (array) $products ) . ' product(s) received, ' . $updated . ' updated' );
	}

	/**
	 * اعمال قیمت/موجودی روی محصول ووکامرس (بر اساس SKU).
	 *
	 * @return int ۱ اگر آپدیت شد، ۰ اگر یافت نشد.
	 */
	private static function apply_to_wc( array $p ): int {
		$sku = isset( $p['sku'] ) ? trim( (string) $p['sku'] ) : '';
		if ( '' === $sku ) {
			return 0;
		}

		$product_id = wc_get_product_id_by_sku( $sku );
		if ( ! $product_id ) {
			return 0;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return 0;
		}

		$price = isset( $p['channel_price'] ) ? (float) $p['channel_price'] : 0;
		$stock = isset( $p['stock'] ) ? (int) $p['stock'] : 0;

		$changed = false;

		if ( $price > 0 && (float) $product->get_regular_price( 'edit' ) !== $price ) {
			$product->set_regular_price( $price );
			$changed = true;
		}

		if ( $product->get_manage_stock( 'edit' ) ) {
			if ( $product->get_stock_quantity( 'edit' ) !== $stock ) {
				$product->set_stock_quantity( $stock );
				$changed = true;
			}
		}

		if ( $changed ) {
			$product->save();
			return 1;
		}

		return 0;
	}
}
