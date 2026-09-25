<?php
/**
 * سرویس موجودی انبار — تعدیل، کسر خودکار سفارش و بازگردانی.
 *
 * @package Bespari\Modules\Warehouse
 */

namespace Bespari\Modules\Warehouse;

use Bespari\Modules\Order\OrderRepository;
use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StockService {

	private StockMovementRepository $repo;

	public function __construct() {
		$this->repo = new StockMovementRepository();
	}

	/**
	 * لیست گردش موجودی.
	 */
	public function list_movements( array $args = array() ): array {
		$page    = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters = array_intersect_key( $args, array_flip( array( 'product_id', 'type', 'ref_type', 'date_from', 'date_to' ) ) );
		return $this->repo->paginate( $filters, $page, $per_page );
	}

	/**
	 * لیست محصولات با موجودی (فیلتر کم‌موجودی/جستجو/برند).
	 *
	 * @return array{items:array, total:int}
	 */
	public function list_products( array $args = array() ): array {
		global $wpdb;
		$pr = Helpers::table( 'products' );
		$br = Helpers::table( 'brands' );

		$search = isset( $args['search'] ) ? sanitize_text_field( wp_unslash( $args['search'] ) ) : '';
		$low    = ! empty( $args['low_stock'] );
		$brand  = (int) ( $args['brand_id'] ?? 0 );
		$page   = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per    = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );

		$where  = array( 'p.status = 1' );
		$values = array();

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(p.name LIKE %s OR p.sku LIKE %s OR p.product_code LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
		if ( $brand ) {
			$where[]  = 'p.brand_id = %d';
			$values[] = $brand;
		}
		if ( $low ) {
			$where[] = 'p.stock <= p.low_stock_threshold';
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$pr} p WHERE {$where_sql}", $values ) ); // phpcs:ignore
		$offset    = ( $page - 1 ) * $per;

		$sql  = "SELECT p.*, b.name AS brand_name FROM {$pr} p
			LEFT JOIN {$br} b ON b.id = p.brand_id
			WHERE {$where_sql}
			ORDER BY (p.stock <= p.low_stock_threshold) DESC, p.name ASC
			LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $values, array( $per, $offset ) ) ) ); // phpcs:ignore

		return array(
			'items' => $rows ? array_map( '\Bespari\Modules\Product\ProductModel::from_row', $rows ) : array(),
			'total' => $total,
		);
	}

	/**
	 * شمارش محصولات کم‌موجود (برای داشبورد).
	 */
	public function count_low_stock(): int {
		global $wpdb;
		$pr = Helpers::table( 'products' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$pr} WHERE status = 1 AND stock <= low_stock_threshold" ); // phpcs:ignore
	}

	/**
	 * تعدیل دستی موجودی + ثبت گردش.
	 *
	 * @param int    $product_id
	 * @param string $type      in|out|adjust
	 * @param int    $quantity  برای adjust = مقدار نهایی؛ برای in/out مقدار تغییر.
	 * @param string $notes
	 * @return true|\WP_Error
	 */
	public function adjust( int $product_id, string $type, int $quantity, string $notes = '' ) {
		global $wpdb;

		if ( ! in_array( $type, array( 'in', 'out', 'adjust' ), true ) ) {
			return new \WP_Error( 'invalid_type', __( 'نوع گردش نامعتبر است.', 'bespari-core' ) );
		}
		if ( 'adjust' !== $type && $quantity <= 0 ) {
			return new \WP_Error( 'invalid_quantity', __( 'تعداد باید بزرگ‌تر از صفر باشد.', 'bespari-core' ) );
		}
		if ( 'adjust' === $type && $quantity < 0 ) {
			return new \WP_Error( 'invalid_quantity', __( 'موجودی نهایی نمی‌تواند منفی باشد.', 'bespari-core' ) );
		}

		$pr_table = Helpers::table( 'products' );
		$product  = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, stock FROM {$pr_table} WHERE id = %d", $product_id ) ); // phpcs:ignore
		if ( ! $product ) {
			return new \WP_Error( 'not_found', __( 'محصول یافت نشد.', 'bespari-core' ) );
		}

		$before = (int) $product->stock;
		switch ( $type ) {
			case 'in':
				$after = $before + $quantity;
				break;
			case 'out':
				$after = $before - $quantity;
				break;
			default:
				$after = $quantity;
		}

		$wpdb->update( $pr_table, array( 'stock' => $after, 'updated_at' => Helpers::now() ), array( 'id' => $product_id ) );

		$mv_id = $this->insert_movement( array(
			'product_id'   => $product_id,
			'type'         => $type,
			'quantity'     => $after - $before,
			'stock_before' => $before,
			'stock_after'  => $after,
			'ref_type'     => 'manual',
			'ref_id'       => 0,
			'notes'        => $notes,
			'user_id'      => get_current_user_id(),
		) );

		AuditLog::log( 'adjust', 'stock', $product_id, array( 'stock' => $before ), array( 'stock' => $after, 'movement_id' => $mv_id, 'type' => $type ) );

		return true;
	}

	/**
	 * کسر خودکار موجودی آیتم‌های سفارش (هوک bespari_order_created).
	 */
	public function deduct_for_order( int $order_id ): void {
		if ( ! (int) Helpers::get_setting( 'stock_auto_deduct', 1 ) ) {
			return;
		}

		$order_repo = new OrderRepository();
		$items      = $order_repo->get_items( $order_id );
		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $it ) {
			$product_id = (int) $it->product_id;
			if ( ! $product_id ) {
				continue;
			}
			// گارد دوبل‌کاری.
			if ( $this->repo->exists_for_ref_product( 'order', $order_id, $product_id ) ) {
				continue;
			}
			$this->move( $product_id, 'out', (int) $it->quantity, 'order', $order_id, __( 'کسر خودکار سفارش', 'bespari-core' ) );
		}
	}

	/**
	 * بازگردانی موجودی هنگام کنسل شدن سفارش (هوک bespari_order_status_cancelled).
	 */
	public function restore_for_order( int $order_id ): void {
		if ( ! (int) Helpers::get_setting( 'stock_auto_deduct', 1 ) ) {
			return;
		}

		// فقط سفارش‌هایی که قبلاً کسر شده‌اند بازگردانی می‌شوند.
		$existing = $this->repo->find_for_ref( 'order', $order_id );
		if ( empty( $existing ) ) {
			return;
		}

		$order_repo = new OrderRepository();
		$items      = $order_repo->get_items( $order_id );
		foreach ( $items as $it ) {
			$product_id = (int) $it->product_id;
			if ( ! $product_id ) {
				continue;
			}
			if ( ! $this->repo->exists_for_ref_product( 'order', $order_id, $product_id ) ) {
				continue;
			}
			$this->move( $product_id, 'in', (int) $it->quantity, 'order', $order_id, __( 'بازگردانی سفارش لغوشده', 'bespari-core' ) );
		}
	}

	/**
	 * تغییر موجودی یک محصول + ثبت گردش.
	 *
	 * @param string $type     in|out|adjust
	 * @param int    $quantity برای adjust = مقدار نهایی.
	 */
	public function move( int $product_id, string $type, int $quantity, string $ref_type, int $ref_id, string $notes = '' ): void {
		global $wpdb;

		if ( ! in_array( $type, array( 'in', 'out', 'adjust' ), true ) ) {
			return;
		}
		if ( 'adjust' !== $type && $quantity <= 0 ) {
			return;
		}

		$pr_table = Helpers::table( 'products' );
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT id, stock FROM {$pr_table} WHERE id = %d", $product_id ) ); // phpcs:ignore
		if ( ! $row ) {
			return;
		}
		$before = (int) $row->stock;
		switch ( $type ) {
			case 'in':
				$after = $before + $quantity;
				break;
			case 'out':
				$after = $before - $quantity;
				break;
			default:
				$after = $quantity;
		}

		$wpdb->update( $pr_table, array( 'stock' => $after, 'updated_at' => Helpers::now() ), array( 'id' => $product_id ) );

		$this->insert_movement( array(
			'product_id'   => $product_id,
			'type'         => $type,
			'quantity'     => $after - $before,
			'stock_before' => $before,
			'stock_after'  => $after,
			'ref_type'     => $ref_type,
			'ref_id'       => $ref_id,
			'notes'        => $notes,
			'user_id'      => get_current_user_id(),
		) );
	}

	/**
	 * درج رکورد گردش.
	 */
	private function insert_movement( array $data ): int {
		return $this->repo->insert( array(
			'product_id'   => (int) ( $data['product_id'] ?? 0 ),
			'type'         => sanitize_key( $data['type'] ?? 'in' ),
			'quantity'     => (int) ( $data['quantity'] ?? 0 ),
			'stock_before' => (int) ( $data['stock_before'] ?? 0 ),
			'stock_after'  => (int) ( $data['stock_after'] ?? 0 ),
			'ref_type'     => sanitize_key( $data['ref_type'] ?? '' ),
			'ref_id'       => (int) ( $data['ref_id'] ?? 0 ),
			'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
			'user_id'      => (int) ( $data['user_id'] ?? 0 ),
			'created_at'   => Helpers::now(),
		) );
	}
}
