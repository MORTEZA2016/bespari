<?php
/**
 * سرویس مرجوعی‌ها — ثبت، تایید (فریز مبلغ از snapshot) و بازگشت به انبار.
 *
 * @package Bespari\Modules\Returns
 */

namespace Bespari\Modules\Returns;

use Bespari\Modules\Order\OrderRepository;
use Bespari\Modules\Warehouse\StockService;
use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReturnService {

	private ReturnRepository $repo;

	public function __construct() {
		$this->repo = new ReturnRepository();
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

	public function get( int $id ): ?ReturnModel {
		return $this->repo->get_by_id( $id );
	}

	/**
	 * ثبت مرجوعی جدید.
	 *
	 * @param array $input order_id, product_id, quantity, reason, notes
	 * @return int|\WP_Error
	 */
	public function save( array $input ) {
		$order_id   = (int) ( $input['order_id'] ?? 0 );
		$product_id = (int) ( $input['product_id'] ?? 0 );
		$quantity   = max( 1, (int) ( $input['quantity'] ?? 1 ) );
		$reason     = isset( $input['reason'] ) ? sanitize_text_field( wp_unslash( $input['reason'] ) ) : '';
		$notes      = isset( $input['notes'] ) ? sanitize_textarea_field( wp_unslash( $input['notes'] ) ) : '';

		if ( ! $order_id || ! $product_id ) {
			return new \WP_Error( 'missing_ref', __( 'انتخاب سفارش و محصول الزامی است.', 'bespari-core' ) );
		}

		$order_repo = new OrderRepository();
		$order      = $order_repo->get_full( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'invalid_order', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}
		if ( 'cancelled' === $order->status ) {
			return new \WP_Error( 'order_cancelled', __( 'سفارش لغوشده قابل مرجوعی نیست.', 'bespari-core' ) );
		}

		// یافتن آیتم سفارش.
		$item = null;
		foreach ( $order->items as $it ) {
			if ( (int) $it->product_id === $product_id ) {
				$item = $it;
				break;
			}
		}
		if ( ! $item ) {
			return new \WP_Error( 'invalid_product', __( 'این محصول در سفارش نیست.', 'bespari-core' ) );
		}

		// گارد سقف مرجوعی: قبلی تاییدشده + جدید <= تعداد سفارش.
		$already = $this->repo->approved_quantity( $order_id, $product_id );
		if ( $already + $quantity > (int) $item->quantity ) {
			return new \WP_Error( 'exceeds_quantity', sprintf( /* translators: 1: already returned, 2: ordered quantity */ __( 'سقف مرجوعی: %1$d از %2$d قبلاً مرجوع شده است.', 'bespari-core' ), $already, (int) $item->quantity ) );
		}

		$now = Helpers::now();
		$id  = $this->repo->insert( array(
			'order_id'      => $order_id,
			'product_id'    => $product_id,
			'quantity'      => $quantity,
			'reason'        => $reason,
			'refund_amount' => 0,
			'status'        => 'pending',
			'notes'         => $notes,
			'created_at'    => $now,
			'updated_at'    => $now,
		) );

		if ( $id ) {
			AuditLog::created( 'return', $id, array( 'order_id' => $order_id, 'product_id' => $product_id, 'quantity' => $quantity ) );
		}

		return $id ?: new \WP_Error( 'insert_failed', __( 'خطا در ثبت مرجوعی.', 'bespari-core' ) );
	}

	/**
	 * تایید مرجوعی — مبلغ بازگشت از snapshot آیتم سفارش فریز می‌شود.
	 *
	 * @return true|\WP_Error
	 */
	public function approve( int $id ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'مرجوعی یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' !== $m->status ) {
			return new \WP_Error( 'invalid_status', __( 'فقط مرجوعی در انتظار قابل تایید است.', 'bespari-core' ) );
		}

		// مبلغ بازگشت = unit_price آیتم سفارش × تعداد مرجوعی (فریز).
		$order_repo = new OrderRepository();
		$items      = $order_repo->get_items( $m->order_id );
		$unit_price = 0.0;
		foreach ( $items as $it ) {
			if ( (int) $it->product_id === $m->product_id ) {
				$unit_price = (float) $it->unit_price;
				break;
			}
		}
		$refund = round( $unit_price * $m->quantity, 2 );

		$ok = $this->repo->update( $id, array(
			'refund_amount' => $refund,
			'status'        => 'approved',
			'updated_at'    => Helpers::now(),
		) );
		if ( $ok ) {
			AuditLog::log( 'approve', 'return', $id, array( 'status' => 'pending' ), array( 'status' => 'approved', 'refund_amount' => $refund ) );

			// بازمحاسبه مبالغ محموله/صورت‌حساب کانالی پس از مرجوعی.
			do_action( 'bespari_return_approved', $m->order_id, $refund );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در تایید مرجوعی.', 'bespari-core' ) );
	}

	public function reject( int $id ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'مرجوعی یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' !== $m->status ) {
			return new \WP_Error( 'invalid_status', __( 'فقط مرجوعی در انتظار قابل رد است.', 'bespari-core' ) );
		}
		$ok = $this->repo->update( $id, array( 'status' => 'rejected', 'updated_at' => Helpers::now() ) );
		if ( $ok ) {
			AuditLog::log( 'reject', 'return', $id, array( 'status' => 'pending' ), array( 'status' => 'rejected' ) );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در رد مرجوعی.', 'bespari-core' ) );
	}

	/**
	 * بازگشت به انبار — موجودی + گردش in.
	 *
	 * @return true|\WP_Error
	 */
	public function restock( int $id ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'مرجوعی یافت نشد.', 'bespari-core' ) );
		}
		if ( 'approved' !== $m->status ) {
			return new \WP_Error( 'invalid_status', __( 'فقط مرجوعی تاییدشده قابل بازگشت به انبار است.', 'bespari-core' ) );
		}
		if ( $m->restocked_at ) {
			return new \WP_Error( 'already_restocked', __( 'این مرجوعی قبلاً به انبار بازگشته است.', 'bespari-core' ) );
		}

		$now = Helpers::now();
		( new StockService() )->move( $m->product_id, 'in', $m->quantity, 'return', $m->id, sprintf( /* translators: %d: return id */ __( 'بازگشت مرجوعی #%d', 'bespari-core' ), $m->id ) );

		$ok = $this->repo->update( $id, array(
			'status'       => 'restocked',
			'restocked_at' => $now,
			'updated_at'   => $now,
		) );
		if ( $ok ) {
			AuditLog::log( 'restock', 'return', $id, array( 'status' => 'approved' ), array( 'status' => 'restocked', 'quantity' => $m->quantity ) );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در بازگشت به انبار.', 'bespari-core' ) );
	}
}
