<?php
/**
 * ماژول اعلان‌های هوشمند — گوش دادن به هوک‌های بسپاری.
 *
 * @package Bespari\Modules\Notification
 */

namespace Bespari\Modules\Notification;

use Bespari\Support\Helpers;
use Bespari\Modules\Order\OrderRepository;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notification {

	private NotificationService $service;

	public function __construct() {
		$this->service = new NotificationService();
	}

	public function register(): void {
		// سفارش جدید → ادمین‌ها + بازاریابِ سفارش.
		add_action( 'bespari_order_created', array( $this, 'on_order_created' ), 10, 2 );

		// تایید پورسانت → بازاریاب.
		add_action( 'bespari_commission_approved', array( $this, 'on_commission_approved' ), 10, 3 );

		// پرداخت پورسانت → بازاریاب.
		add_action( 'bespari_payout_paid', array( $this, 'on_payout_paid' ), 10, 3 );

		// تایید مرجوعی → ادمین‌ها.
		add_action( 'bespari_return_approved', array( $this, 'on_return_approved' ), 10, 2 );

		// لغو سفارش → ادمین‌ها.
		add_action( 'bespari_order_status_cancelled', array( $this, 'on_order_cancelled' ), 10, 2 );

		// پاکسازی دوره‌ای اعلان‌های قدیمی.
		add_action( 'bespari_daily_cleanup', array( $this, 'on_cleanup' ) );
	}

	/**
	 * سفارش جدید.
	 *
	 * @param int   $order_id شناسه سفارش.
	 * @param array $row      داده سفارش.
	 */
	public function on_order_created( int $order_id, array $row ): void {
		$order = ( new OrderRepository() )->get_by_id( $order_id );
		if ( ! $order ) {
			return;
		}

		$amount = Helpers::format_money( (float) ( $order->total_net ?? 0 ) );
		$title  = sprintf( __( 'سفارش جدید: %s', 'bespari-core' ), $order->order_number );
		$body   = sprintf(
			__( 'سفارش %s با مبلغ %s در کانال %s ثبت شد.', 'bespari-core' ),
			$order->order_number,
			$amount,
			$order->channel_name ?: '—'
		);
		$link   = admin_url( 'admin.php?page=bespari-orders&id=' . $order_id );

		// ادمین‌ها.
		$this->service->notify_cap( 'bespari_manage_orders', array(
			'type'  => 'order',
			'title' => $title,
			'body'  => $body,
			'link'  => $link,
		) );

		// بازاریابِ سفارش.
		if ( ! empty( $order->seller_id ) ) {
			$this->service->create( array(
				'user_id' => (int) $order->seller_id,
				'type'    => 'order',
				'title'   => $title,
				'body'    => $body,
				'link'    => AppPanelLink::for_seller( $order->seller_id ),
			) );
		}
	}

	/**
	 * تایید پورسانت.
	 *
	 * @param int   $id        شناسه کمیسیون.
	 * @param int   $seller_id بازاریاب.
	 * @param float $amount    مبلغ.
	 */
	public function on_commission_approved( int $id, int $seller_id, float $amount ): void {
		if ( $seller_id < 1 ) {
			return;
		}

		$this->service->create( array(
			'user_id' => $seller_id,
			'type'    => 'commission',
			'title'   => __( 'پورسانت تایید شد', 'bespari-core' ),
			'body'    => sprintf(
				__( 'پورسانت شما به مبلغ %s تایید شد.', 'bespari-core' ),
				Helpers::format_money( $amount )
			),
			'link'    => AppPanelLink::for_seller( $seller_id ),
		) );
	}

	/**
	 * پرداخت پورسانت.
	 *
	 * @param int   $id        شناسه پرداخت.
	 * @param int   $seller_id بازاریاب.
	 * @param float $amount    مبلغ.
	 */
	public function on_payout_paid( int $id, int $seller_id, float $amount ): void {
		if ( $seller_id < 1 ) {
			return;
		}

		$this->service->create( array(
			'user_id' => $seller_id,
			'type'    => 'payout',
			'title'   => __( 'پرداخت انجام شد', 'bespari-core' ),
			'body'    => sprintf(
				__( 'مبلغ %s به حسابتان پرداخت شد.', 'bespari-core' ),
				Helpers::format_money( $amount )
			),
			'link'    => AppPanelLink::for_seller( $seller_id ),
		) );
	}

	/**
	 * تایید مرجوعی.
	 *
	 * @param int   $order_id شناسه سفارش.
	 * @param float $refund   مبلغ بازگشتی.
	 */
	public function on_return_approved( int $order_id, float $refund = 0 ): void {
		$order = ( new OrderRepository() )->get_by_id( $order_id );
		$label = $order ? $order->order_number : ( '#' . $order_id );

		$this->service->notify_cap( 'bespari_manage_returns', array(
			'type'  => 'return',
			'title' => __( 'مرجوعی تایید شد', 'bespari-core' ),
			'body'  => sprintf(
				__( 'مرجوعی سفارش %s به مبلغ %s تایید شد.', 'bespari-core' ),
				$label,
				Helpers::format_money( $refund )
			),
			'link'  => admin_url( 'admin.php?page=bespari-returns' ),
		) );
	}

	/**
	 * لغو سفارش.
	 *
	 * @param int   $order_id شناسه سفارش.
	 * @param array $row      داده.
	 */
	public function on_order_cancelled( int $order_id, array $row = array() ): void {
		$order = ( new OrderRepository() )->get_by_id( $order_id );
		$label = $order ? $order->order_number : ( '#' . $order_id );

		$this->service->notify_cap( 'bespari_manage_orders', array(
			'type'  => 'order',
			'title' => __( 'سفارش لغو شد', 'bespari-core' ),
			'body'  => sprintf( __( 'سفارش %s لغو شد.', 'bespari-core' ), $label ),
			'link'  => admin_url( 'admin.php?page=bespari-orders&id=' . $order_id ),
		) );
	}

	/**
	 * پاکسازی اعلان‌های قدیمی.
	 */
	public function on_cleanup(): void {
		$this->service->prune( 60 );
	}
}
