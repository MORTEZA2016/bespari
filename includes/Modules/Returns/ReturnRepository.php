<?php
/**
 * Repository مرجوعی‌ها.
 *
 * @package Bespari\Modules\Returns
 */

namespace Bespari\Modules\Returns;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReturnRepository extends BaseRepository {

	protected string $table       = 'returns';
	protected string $model_class = ReturnModel::class;

	public function get_by_id( int $id ): ?ReturnModel {
		global $wpdb;
		$table = $this->table();
		$or    = Helpers::table( 'orders' );
		$pr    = Helpers::table( 'products' );
		$row   = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
			"SELECT r.*, o.order_number, p.name AS product_name, p.sku AS sku
			FROM {$table} r
			LEFT JOIN {$or} o ON o.id = r.order_id
			LEFT JOIN {$pr} p ON p.id = r.product_id
			WHERE r.id = %d",
			$id
		) );
		return $row ? ReturnModel::from_row( $row ) : null;
	}

	/**
	 * جمع مرجوعی‌های تاییدشده برای (سفارش، محصول) — گارد سقف مرجوعی.
	 */
	public function approved_quantity( int $order_id, int $product_id ): int {
		global $wpdb;
		$table = $this->table();
		return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
			"SELECT COALESCE(SUM(quantity), 0) FROM {$table}
			WHERE order_id = %d AND product_id = %d AND status IN ('approved', 'restocked')",
			$order_id,
			$product_id
		) );
	}

	/**
	 * لیست صفحه‌بندی‌شده با فیلتر.
	 *
	 * @param array $filters search (order_number/product), status, date_from, date_to
	 * @return array{items:ReturnModel[], total:int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = $this->table();
		$or    = Helpers::table( 'orders' );
		$pr    = Helpers::table( 'products' );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[]  = '(o.order_number LIKE %s OR p.name LIKE %s OR p.sku LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'r.status = %s';
			$values[] = sanitize_key( $filters['status'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'r.created_at >= %s';
			$values[] = $filters['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'r.created_at <= %s';
			$values[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} r LEFT JOIN {$or} o ON o.id = r.order_id LEFT JOIN {$pr} p ON p.id = r.product_id WHERE {$where_sql}", $values ) ); // phpcs:ignore
		$offset    = ( max( 1, $page ) - 1 ) * $per_page;

		$sql  = "SELECT r.*, o.order_number, p.name AS product_name, p.sku AS sku
			FROM {$table} r
			LEFT JOIN {$or} o ON o.id = r.order_id
			LEFT JOIN {$pr} p ON p.id = r.product_id
			WHERE {$where_sql}
			ORDER BY r.id DESC
			LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $values, array( $per_page, $offset ) ) ) ); // phpcs:ignore
		$items = $rows ? array_map( array( ReturnModel::class, 'from_row' ), $rows ) : array();

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
