<?php
/**
 * Repository برگه‌های ارسال.
 *
 * @package Bespari\Modules\Shipping
 */

namespace Bespari\Modules\Shipping;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShipmentRepository extends BaseRepository {

	protected string $table       = 'shipments';
	protected string $model_class = ShipmentModel::class;

	public function get_by_id( int $id ): ?ShipmentModel {
		global $wpdb;
		$table = $this->table();
		$or    = Helpers::table( 'orders' );
		$row   = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
			"SELECT s.*, o.order_number, o.customer_name, o.customer_phone, o.customer_address
			FROM {$table} s LEFT JOIN {$or} o ON o.id = s.order_id
			WHERE s.id = %d",
			$id
		) );
		return $row ? ShipmentModel::from_row( $row ) : null;
	}

	/**
	 * برگه فعال یک سفارش.
	 */
	public function find_by_order( int $order_id ): ?ShipmentModel {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d AND status NOT IN ('cancelled') ORDER BY id DESC LIMIT 1", $order_id ) ); // phpcs:ignore
		return $row ? ShipmentModel::from_row( $row ) : null;
	}

	/**
	 * لیست صفحه‌بندی‌شده با فیلتر.
	 *
	 * @param array $filters search (tracking/order_number/customer), status, date_from, date_to
	 * @return array{items:ShipmentModel[], total:int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = $this->table();
		$or    = Helpers::table( 'orders' );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[]  = '(s.tracking_code LIKE %s OR o.order_number LIKE %s OR o.customer_name LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 's.status = %s';
			$values[] = sanitize_key( $filters['status'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 's.created_at >= %s';
			$values[] = $filters['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 's.created_at <= %s';
			$values[] = $filters['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} s LEFT JOIN {$or} o ON o.id = s.order_id WHERE {$where_sql}", $values ) ); // phpcs:ignore
		$offset    = ( max( 1, $page ) - 1 ) * $per_page;

		$sql  = "SELECT s.*, o.order_number, o.customer_name
			FROM {$table} s LEFT JOIN {$or} o ON o.id = s.order_id
			WHERE {$where_sql}
			ORDER BY s.id DESC
			LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $values, array( $per_page, $offset ) ) ) ); // phpcs:ignore
		$items = $rows ? array_map( array( ShipmentModel::class, 'from_row' ), $rows ) : array();

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
