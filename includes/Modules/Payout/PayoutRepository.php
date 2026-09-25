<?php
/**
 * Repository پرداخت‌ها.
 *
 * @package Bespari\Modules\Payout
 */

namespace Bespari\Modules\Payout;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayoutRepository extends BaseRepository {

	protected string $table       = 'payouts';
	protected string $model_class = PayoutModel::class;

	public function get_by_id( int $id ): ?PayoutModel {
		$row = $this->find( $id );
		return $row ? PayoutModel::from_row( $row ) : null;
	}

	/**
	 * @param array $filters seller_id, status, date_from, date_to
	 * @return array{items:PayoutModel[],total:int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table   = $this->table();
		$u_table = $wpdb->users;

		$where  = array();
		$values = array();

		if ( ! empty( $filters['seller_id'] ) ) {
			$where[]  = 'p.seller_id = %d';
			$values[] = (int) $filters['seller_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'p.status = %s';
			$values[] = sanitize_key( $filters['status'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'p.created_at >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'p.created_at <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] );
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$count_sql = "SELECT COUNT(*) FROM {$table} p {$where_sql}";
		$total     = $values ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore

		$offset       = ( $page - 1 ) * $per_page;
		$values_paged = array_merge( $values, array( $per_page, $offset ) );

		$sql  = "SELECT p.*, u.display_name AS seller_name FROM {$table} p LEFT JOIN {$u_table} u ON u.ID = p.seller_id";
		$sql .= " {$where_sql} ORDER BY p.id DESC LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values_paged ) ); // phpcs:ignore

		return array(
			'items' => $this->hydrate( (array) $rows ),
			'total' => $total,
		);
	}
}
