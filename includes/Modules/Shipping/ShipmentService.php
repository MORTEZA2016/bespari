<?php
/**
 * سرویس برگه‌های ارسال.
 *
 * @package Bespari\Modules\Shipping
 */

namespace Bespari\Modules\Shipping;

use Bespari\Modules\Order\OrderRepository;
use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShipmentService {

	private ShipmentRepository $repo;

	public function __construct() {
		$this->repo = new ShipmentRepository();
	}

	/**
	 * لیست صفحه‌بندی‌شده.
	 */
	public function list( array $args = array() ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters  = array_intersect_key( $args, array_flip( array( 'search', 'status', 'date_from', 'date_to' ) ) );
		return $this->repo->paginate( $filters, $page, $per_page );
	}

	public function get( int $id ): ?ShipmentModel {
		return $this->repo->get_by_id( $id );
	}

	/**
	 * ایجاد یا به‌روزرسانی برگه ارسال.
	 *
	 * @param int   $id    صفر = ایجاد.
	 * @param array $input order_id, tracking_code, carrier, notes
	 * @return int|\WP_Error
	 */
	public function save( int $id, array $input ) {
		$order_id     = (int) ( $input['order_id'] ?? 0 );
		$tracking_code = isset( $input['tracking_code'] ) ? sanitize_text_field( wp_unslash( $input['tracking_code'] ) ) : '';
		$carrier      = isset( $input['carrier'] ) ? sanitize_text_field( wp_unslash( $input['carrier'] ) ) : '';
		$notes        = isset( $input['notes'] ) ? sanitize_textarea_field( wp_unslash( $input['notes'] ) ) : '';

		if ( $id ) {
			$m = $this->repo->get_by_id( $id );
			if ( ! $m ) {
				return new \WP_Error( 'not_found', __( 'برگه ارسال یافت نشد.', 'bespari-core' ) );
			}
			if ( in_array( $m->status, array( 'delivered', 'cancelled' ), true ) ) {
				return new \WP_Error( 'not_editable', __( 'برگه تحویل‌شده یا لغوشده قابل ویرایش نیست.', 'bespari-core' ) );
			}
			$ok = $this->repo->update( $id, array(
				'tracking_code' => $tracking_code,
				'carrier'       => $carrier,
				'notes'         => $notes,
				'updated_at'    => Helpers::now(),
			) );
			if ( $ok ) {
				AuditLog::log( 'updated', 'shipment', $id, array(), array( 'tracking_code' => $tracking_code, 'carrier' => $carrier ) );
			}
			return $ok ? $id : new \WP_Error( 'update_failed', __( 'خطا در به‌روزرسانی برگه.', 'bespari-core' ) );
		}

		if ( ! $order_id ) {
			return new \WP_Error( 'missing_order', __( 'انتخاب سفارش الزامی است.', 'bespari-core' ) );
		}

		// گارد: سفارش باید موجود باشد.
		$order_repo = new OrderRepository();
		if ( ! $order_repo->get_by_id( $order_id ) ) {
			return new \WP_Error( 'invalid_order', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}

		// گارد: هر سفارش حداکثر یک برگه فعال.
		if ( $this->repo->find_by_order( $order_id ) ) {
			return new \WP_Error( 'duplicate', __( 'این سفارش قبلاً برگه ارسال فعال دارد.', 'bespari-core' ) );
		}

		$now = Helpers::now();
		$new_id = $this->repo->insert( array(
			'order_id'      => $order_id,
			'tracking_code' => $tracking_code,
			'carrier'       => $carrier,
			'status'        => 'pending',
			'notes'         => $notes,
			'created_at'    => $now,
			'updated_at'    => $now,
		) );

		if ( $new_id ) {
			AuditLog::created( 'shipment', $new_id, array( 'order_id' => $order_id, 'tracking_code' => $tracking_code, 'carrier' => $carrier ) );
		}

		return $new_id ?: new \WP_Error( 'insert_failed', __( 'خطا در ثبت برگه ارسال.', 'bespari-core' ) );
	}

	/**
	 * تغییر وضعیت برگه + timestamp مربوطه.
	 *
	 * @return true|\WP_Error
	 */
	public function update_status( int $id, string $status ) {
		$allowed = array( 'pending', 'prepared', 'shipped', 'delivered', 'failed', 'cancelled' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new \WP_Error( 'invalid_status', __( 'وضعیت نامعتبر است.', 'bespari-core' ) );
		}

		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'برگه ارسال یافت نشد.', 'bespari-core' ) );
		}
		if ( 'delivered' === $m->status ) {
			return new \WP_Error( 'final_status', __( 'برگه تحویل‌شده قابل تغییر نیست.', 'bespari-core' ) );
		}

		$data = array( 'status' => $status, 'updated_at' => Helpers::now() );
		$now  = Helpers::now();
		if ( 'shipped' === $status ) {
			$data['shipped_at'] = $now;
		}
		if ( 'delivered' === $status ) {
			$data['delivered_at'] = $now;
			if ( empty( $m->shipped_at ) ) {
				$data['shipped_at'] = $now;
			}
		}

		$ok = $this->repo->update( $id, $data );
		if ( $ok ) {
			AuditLog::log( 'status_changed', 'shipment', $id, array( 'status' => $m->status ), array( 'status' => $status ) );

			// همگام‌سازی وضعیت سفارش: delivered → وضعیت سفارش هم delivered شود.
			if ( 'delivered' === $status ) {
				$order_repo = new OrderRepository();
				$order      = $order_repo->get_by_id( $m->order_id );
				if ( $order && in_array( $order->status, array( 'pending', 'confirmed', 'shipped' ), true ) ) {
					$order_repo->update( $order->id, array( 'status' => 'delivered', 'updated_at' => $now ) );
				}
			}
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در تغییر وضعیت برگه.', 'bespari-core' ) );
	}

	/**
	 * حذف برگه (فقط pending/cancelled/failed).
	 */
	public function delete( int $id ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'برگه ارسال یافت نشد.', 'bespari-core' ) );
		}
		if ( ! in_array( $m->status, array( 'pending', 'cancelled', 'failed' ), true ) ) {
			return new \WP_Error( 'not_deletable', __( 'فقط برگه‌های در انتظار/لغوشده/ناموفق قابل حذف هستند.', 'bespari-core' ) );
		}
		$ok = $this->repo->delete( $id );
		if ( $ok ) {
			AuditLog::log( 'deleted', 'shipment', $id, array( 'order_id' => $m->order_id, 'status' => $m->status ), array() );
		}
		return $ok ? true : new \WP_Error( 'delete_failed', __( 'خطا در حذف برگه.', 'bespari-core' ) );
	}
}
