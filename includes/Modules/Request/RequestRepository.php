<?php
/**
 * Repository درخواست‌ها.
 *
 * @package Bespari\Modules\Request
 */

namespace Bespari\Modules\Request;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RequestRepository extends BaseRepository {

	protected string $table       = 'requests';
	protected string $model_class = RequestModel::class;

	public function get_by_id( int $id ): ?RequestModel {
		global $wpdb;
		$table = $this->table();
		$pr    = Helpers::table( 'products' );
		$or    = Helpers::table( 'orders' );
		$row   = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore
			"SELECT r.*, p.name AS product_name, o.order_number
			FROM {$table} r
			LEFT JOIN {$pr} p ON p.id = r.product_id
			LEFT JOIN {$or} o ON o.id = r.order_id
			WHERE r.id = %d",
			$id
		) );
		return $row ? RequestModel::from_row( $row ) : null;
	}

	/**
	 * لیست صفحه‌بندی‌شده با فیلتر.
	 *
	 * @param array $filters search, type, status, requester_id, date_from, date_to
	 * @return array{items:RequestModel[], total:int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = $this->table();
		$pr    = Helpers::table( 'products' );
		$or    = Helpers::table( 'orders' );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[]  = '(r.title LIKE %s OR r.description LIKE %s OR o.order_number LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
		if ( ! empty( $filters['type'] ) ) {
			$where[]  = 'r.type = %s';
			$values[] = sanitize_key( $filters['type'] );
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'r.status = %s';
			$values[] = sanitize_key( $filters['status'] );
		}
		if ( ! empty( $filters['requester_id'] ) ) {
			$where[]  = 'r.requester_id = %d';
			$values[] = (int) $filters['requester_id'];
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
		$total     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} r LEFT JOIN {$pr} p ON p.id = r.product_id LEFT JOIN {$or} o ON o.id = r.order_id WHERE {$where_sql}", $values ) ); // phpcs:ignore
		$offset    = ( max( 1, $page ) - 1 ) * $per_page;

		$sql  = "SELECT r.*, p.name AS product_name, o.order_number
			FROM {$table} r
			LEFT JOIN {$pr} p ON p.id = r.product_id
			LEFT JOIN {$or} o ON o.id = r.order_id
			WHERE {$where_sql}
			ORDER BY r.id DESC
			LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $values, array( $per_page, $offset ) ) ) ); // phpcs:ignore
		$items = $rows ? array_map( array( RequestModel::class, 'from_row' ), $rows ) : array();

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
