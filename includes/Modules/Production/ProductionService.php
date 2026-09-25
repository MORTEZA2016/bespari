<?php
/**
 * سرویس خط تولید — لیست روزانه، آماده‌سازی، انتقال روز و ارسال.
 *
 * @package Bespari\Modules\Production
 */

namespace Bespari\Modules\Production;

use Bespari\Modules\Order\OrderRepository;
use Bespari\Modules\Order\OrderService;
use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductionService {

	private ProductionRepository $repo;

	public function __construct() {
		$this->repo = new ProductionRepository();
	}

	/**
	 * payload سلسله‌مراتبی خط تولید برای یک روز:
	 * brands[] → channels[] → groups[] → orders[].
	 *
	 * @param string $date روز با فرمت Y-m-d.
	 * @return array
	 */
	public function list_by_date( string $date ): array {
		$rows = $this->repo->list_by_date( $date );

		$order_ids = array();
		foreach ( $rows as $r ) {
			$order_ids[] = (int) $r->id;
		}
		$items_map = $this->repo->get_items_map( $order_ids );

		$brands = array();

		foreach ( $rows as $r ) {
			$brand_id = (int) $r->brand_id;
			$chan_id  = (int) $r->channel_id;

			if ( ! isset( $brands[ $brand_id ] ) ) {
				$brands[ $brand_id ] = array(
					'id'       => $brand_id,
					'name'     => $brand_id > 0 ? (string) $r->brand_name : __( 'سایر', 'bespari-core' ),
					'channels' => array(),
				);
			}

			if ( ! isset( $brands[ $brand_id ]['channels'][ $chan_id ] ) ) {
				$brands[ $brand_id ]['channels'][ $chan_id ] = array(
					'id'             => $chan_id,
					'name'           => (string) ( $r->channel_name ?: '—' ),
					'shipping_window' => (string) ( $r->shipping_window ?? '' ),
					'groups'         => array(),
				);
			}

			$moved    = (int) $r->production_moved === 1;
			$priority = (int) $r->production_priority;
			$group_key = $moved ? 'guest' : (string) $priority;

			if ( ! isset( $brands[ $brand_id ]['channels'][ $chan_id ]['groups'][ $group_key ] ) ) {
				$brands[ $brand_id ]['channels'][ $chan_id ]['groups'][ $group_key ] = array(
					'key'  => $group_key,
					// برچسب گروه: مهمان برای منتقل‌شده‌ها، در غیر این صورت شماره الویت.
					'label' => $moved
						? __( 'سفارشات مهمان', 'bespari-core' )
						: ( $priority > 0
							? sprintf( __( 'الویت %s', 'bespari-core' ), number_format_i18n( $priority ) )
							: __( 'بدون اولویت', 'bespari-core' ) ),
					'orders' => array(),
				);
			}

			$items     = $items_map[ (int) $r->id ] ?? array();
			$qty_total = 0;
			foreach ( $items as $it ) {
				$qty_total += $it['quantity'];
			}

			$brands[ $brand_id ]['channels'][ $chan_id ]['groups'][ $group_key ]['orders'][] = array(
				'id'              => (int) $r->id,
				'order_number'    => (string) $r->order_number,
				'customer_name'   => (string) ( $r->customer_name ?: '—' ),
				'seller_name'     => (string) ( $r->seller_name ?: '—' ),
				'sale_type'       => 'credit' === $r->sale_type ? 'اعتباری' : 'نقدی',
				'items'           => $items,
				'items_count'     => count( $items ),
				'quantity'        => $qty_total,
				'total'           => Helpers::format_money( (float) $r->total_net ),
				'ordered_time'    => mysql2date( 'H:i', (string) $r->ordered_at ),
				'status'          => (string) $r->status,
				'produced'        => 'produced' === $r->status,
				'production_moved' => $moved,
			);
		}

		// تبدیل کلیدهای عددی به لیست + مرتب‌سازی گروه‌ها (مهمان اول، سپس الویت صعودی).
		$brands_out = array();
		foreach ( $brands as $brand ) {
			$channels_out = array();
			foreach ( $brand['channels'] as $channel ) {
				$groups = array_values( $channel['groups'] );
				usort( $groups, static function ( $a, $b ) {
					if ( 'guest' === $a['key'] ) {
						return -1;
					}
					if ( 'guest' === $b['key'] ) {
						return 1;
					}
					return (int) $a['key'] - (int) $b['key'];
				} );
				$channel['groups']    = $groups;
				$channels_out[]       = $channel;
			}
			$brand['channels'] = $channels_out;
			$brands_out[]      = $brand;
		}

		return array(
			'date'    => $date,
			'date_fa' => Helpers::to_jalali_date( $date . ' 00:00:00' ),
			'today'   => current_time( 'Y-m-d' ),
			'brands'  => $brands_out,
		);
	}

	/**
	 * تیک آماده‌شدن سفارش (یا برگشت از آن).
	 *
	 * @param int $order_id شناسه سفارش.
	 * @return bool|\WP_Error
	 */
	public function mark_ready( int $order_id ) {
		$order = ( new OrderRepository() )->get_by_id( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'not_found', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}

		if ( ! in_array( $order->status, array( 'pending', 'confirmed', 'produced' ), true ) ) {
			return new \WP_Error( 'invalid_state', __( 'این سفارش در حال حاضر در خط تولید نیست.', 'bespari-core' ) );
		}

		// اگر قبلاً تولید شده، تیک برمی‌گردد به «در انتظار».
		$service = new OrderService();

		return 'produced' === $order->status
			? $service->update_status( $order_id, 'pending' )
			: $service->update_status( $order_id, 'produced' );
	}

	/**
	 * انتقال سفارش به یک روز آینده (به‌عنوان سفارش مهمان).
	 *
	 * @param int    $order_id شناسه سفارش.
	 * @param string $date     روز مقصد با فرمت Y-m-d.
	 * @return bool|\WP_Error
	 */
	public function move_order( int $order_id, string $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new \WP_Error( 'invalid_date', __( 'تاریخ نامعتبر است.', 'bespari-core' ) );
		}

		$today = current_time( 'Y-m-d' );
		if ( $date <= $today ) {
			return new \WP_Error( 'past_date', __( 'فقط به روزهای بعد از امروز می‌توانید سفارش را منتقل کنید.', 'bespari-core' ) );
		}

		$order = ( new OrderRepository() )->get_by_id( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'not_found', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}
		if ( ! in_array( $order->status, array( 'pending', 'confirmed', 'produced' ), true ) ) {
			return new \WP_Error( 'invalid_state', __( 'این سفارش در حال حاضر در خط تولید نیست.', 'bespari-core' ) );
		}

		$old = array(
			'production_date'  => (string) ( $order->production_date ?? '' ),
			'production_moved' => (int) $order->production_moved,
		);

		$ok = $this->repo->set_schedule( $order_id, $date, true );
		if ( ! $ok ) {
			return new \WP_Error( 'move_failed', __( 'خطا در انتقال سفارش.', 'bespari-core' ) );
		}

		$new = array( 'production_date' => $date, 'production_moved' => 1 );

		AuditLog::log( 'production_move', 'order', $order_id, $old, $new );

		/**
		 * انتقال سفارش به روز دیگر در خط تولید.
		 */
		do_action( 'bespari_order_updated', $order_id, $new );

		return true;
	}

	/**
	 * ثبت ارسال سفارش توسط بازاریاب (فقط سفارش‌های تولیدشده).
	 *
	 * @param int $order_id شناسه سفارش.
	 * @return bool|\WP_Error
	 */
	public function mark_shipped( int $order_id ) {
		$order = ( new OrderRepository() )->get_by_id( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'not_found', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}

		if ( 'produced' !== $order->status ) {
			return new \WP_Error( 'not_produced', __( 'فقط سفارش‌های تولیدشده قابل ارسال هستند.', 'bespari-core' ) );
		}

		// بازاریاب فقط سفارش خودش را ارسال می‌کند.
		if ( ! current_user_can( 'bespari_manage_orders' ) && (int) $order->seller_id !== get_current_user_id() ) {
			return new \WP_Error( 'forbidden', __( 'این سفارش متعلق به شما نیست.', 'bespari-core' ) );
		}

		return ( new OrderService() )->update_status( $order_id, 'shipped' );
	}
}
