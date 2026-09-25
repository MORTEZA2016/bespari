<?php
/**
 * Repository گردش موجودی.
 *
 * @package Bespari\Modules\Warehouse
 */

namespace Bespari\Modules\Warehouse;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StockMovementRepository extends BaseRepository {

	protected string $table       = 'stock_movements';
	protected string $model_class = StockMovementModel::class;

	public function get_by_id( int $id ): ?StockMovementModel {
		$row = $this->find( $id );
		return $row ? StockMovementModel::from_row( $row ) : null;
	}

	/**
	 * آخرین movement برای یک مرجع (مثلاً order_id) — گارد دوبل‌کاری.
	 *
	 * @param string $ref_type
	 * @param int    $ref_id
	 * @return array
	 */
	public function find_for_ref( string $ref_type, int $ref_id ): array {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore
			"SELECT * FROM {$table} WHERE ref_type = %s AND ref_id = %d",
			$ref_type,
			$ref_id
		) );
		return $rows ? array_map( array( StockMovementModel::class, 'from_row' ), $rows ) : array();
	}

	/**
	 * آیا برای (ref, product) قبلاً movement ثبت شده؟
	 */
	public function exists_for_ref_product( string $ref_type, int $ref_id, int $product_id ): bool {
		global $wpdb;
		$table = $this->table();
		return (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
			"SELECT COUNT(*) FROM {$table} WHERE ref_type = %s AND ref_id = %d AND product_id = %d",
			$ref_type,
			$ref_id,
			$product_id
		) );
	}

	/**
	 * لیست صفحه‌بندی‌شده با فیلتر.
	 *
	 * @param array $filters product_id, type, ref_type, date_from, date_to
	 * @return array{items:StockMovementModel[], total:int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = $this->table();
		$pr    = Helpers::table( 'products' );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['product_id'] ) ) {
			$where[]  = 'm.product_id = %d';
			$values[] = (int) $filters['product_id'];
		}
		if ( ! empty( $filters['type'] ) ) {
			$where[]  = 'm.type = %s';
			$values[] = sanitize_key( $filters['type'] );
		}
		if ( ! empty( $filters['ref_type'] ) ) {
			$where[]  = 'm.ref_type = %s';
			$values[] = sanitize_key( $filters['ref_type'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'm.created_at >= %s';
			$values[] = $filters['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'm.created_at <= %s';
			$values[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} m WHERE {$where_sql}", $values ) ); // phpcs:ignore
		$offset    = ( max( 1, $page ) - 1 ) * $per_page;

		$sql = "SELECT m.*, p.name AS product_name, p.sku AS sku
			FROM {$table} m
			LEFT JOIN {$pr} p ON p.id = m.product_id
			WHERE {$where_sql}
			ORDER BY m.id DESC
			LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $values, array( $per_page, $offset ) ) ) ); // phpcs:ignore
		$items = $rows ? array_map( array( StockMovementModel::class, 'from_row' ), $rows ) : array();

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
