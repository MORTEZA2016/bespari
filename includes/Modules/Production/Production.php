<?php
/**
 * ماژول خط تولید — تولید روزانه، الویت‌بندی و اعلان آماده‌شدن.
 *
 * @package Bespari\Modules\Production
 */

namespace Bespari\Modules\Production;

use Bespari\Modules\Notification\NotificationService;
use Bespari\Modules\Notification\AppPanelLink;
use Bespari\Modules\Order\OrderRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Production {

	private ProductionService $service;

	public function __construct() {
		$this->service = new ProductionService();
	}

	public function register(): void {
		// سفارش آماده شد → اعلان به بازاریابِ سفارش.
		add_action( 'bespari_order_produced', array( $this, 'on_order_produced' ), 10, 2 );
	}

	/**
	 * آماده‌شدن سفارش در خط تولید.
	 *
	 * @param int      $order_id شناسه سفارش.
	 * @param object   $existing مدل سفارش قبل از تغییر.
	 */
	public function on_order_produced( int $order_id, $existing ): void {
		$order = ( new OrderRepository() )->get_by_id( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( empty( $order->seller_id ) ) {
			return;
		}

		$amount = Helpers::format_money( (float) $order->total_net );
		$title  = sprintf( __( 'سفارش آماده شد: %s', 'bespari-core' ), $order->order_number );
		$body   = sprintf(
			__( 'سفارش %s با مبلغ %s در کانال %s آماده تحویل است. به محل تحویل مراجعه کنید.', 'bespari-core' ),
			$order->order_number,
			$amount,
			$order->channel_name ?: '—'
		);

		( new NotificationService() )->create( array(
			'user_id' => (int) $order->seller_id,
			'type'    => 'production',
			'title'   => $title,
			'body'    => $body,
			'link'    => AppPanelLink::for_seller( (int) $order->seller_id ),
		) );
	}
}
