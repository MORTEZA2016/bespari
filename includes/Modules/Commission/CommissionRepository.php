<?php
/**
 * Repository پورسانت.
 *
 * @package Bespari\Modules\Commission
 */

namespace Bespari\Modules\Commission;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommissionRepository extends BaseRepository {

	protected string $table       = 'commissions';
	protected string $model_class = CommissionModel::class;

	public function get_by_id( int $id ): ?CommissionModel {
		$row = $this->find( $id );
		return $row ? CommissionModel::from_row( $row ) : null;
	}

	public function find_by_order( int $order_id ): ?CommissionModel {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d LIMIT 1", $order_id ) ); // phpcs:ignore
		return $row ? CommissionModel::from_row( $row ) : null;
	}

	/**
	 * صفحه‌بندی با JOIN سفارش و کاربر.
	 *
	 * @param array $filters seller_id, status, date_from, date_to, search
	 * @param int   $page
	 * @param int   $per_page
	 * @return array{items:CommissionModel[],total:int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table   = $this->table();
		$o_table = Helpers::table( 'orders' );
		$u_table = $wpdb->users;

		$where  = array();
		$values = array();

		if ( ! empty( $filters['seller_id'] ) ) {
			$where[]  = 'c.seller_id = %d';
			$values[] = (int) $filters['seller_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'c.status = %s';
			$values[] = sanitize_key( $filters['status'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'c.created_at >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'c.created_at <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] );
		}
		if ( ! empty( $filters['search'] ) ) {
			$where[]  = 'o.order_number LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$count_sql = "SELECT COUNT(*) FROM {$table} c LEFT JOIN {$o_table} o ON o.id = c.order_id {$where_sql}";
		$total     = $values ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore

		$offset = ( $page - 1 ) * $per_page;
		$values_paged = array_merge( $values, array( $per_page, $offset ) );

		$sql  = "SELECT c.*, o.order_number, u.display_name AS seller_name FROM {$table} c";
		$sql .= " LEFT JOIN {$o_table} o ON o.id = c.order_id";
		$sql .= " LEFT JOIN {$u_table} u ON u.ID = c.seller_id";
		$sql .= " {$where_sql} ORDER BY c.id DESC LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values_paged ) ); // phpcs:ignore

		return array(
			'items' => $this->hydrate( (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * موجودی بازاریاب به تفکیک وضعیت.
	 *
	 * @param int $seller_id
	 * @return array{pending:float,approved:float,paid:float,total:float,cancelled:float}
	 */
	public function get_seller_balance( int $seller_id ): array {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT status, SUM(amount) AS s FROM {$table} WHERE seller_id = %d GROUP BY status", $seller_id ) ); // phpcs:ignore
		$bal   = array( 'pending' => 0.0, 'approved' => 0.0, 'paid' => 0.0, 'total' => 0.0, 'cancelled' => 0.0 );
		foreach ( (array) $rows as $r ) {
			$st = (string) $r->status;
			if ( isset( $bal[ $st ] ) ) {
				$bal[ $st ] = (float) $r->s;
			}
			if ( 'cancelled' !== $st ) {
				$bal['total'] += (float) $r->s;
			}
		}
		return $bal;
	}

	public function get_by_payout( int $payout_id ): array {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE payout_id = %d ORDER BY id ASC", $payout_id ) ); // phpcs:ignore
		return $this->hydrate( (array) $rows );
	}

	/**
	 * کمیسیون‌های approved یک فروشنده به ترتیب FIFO.
	 *
	 * @return CommissionModel[]
	 */
	public function get_approved_for_seller( int $seller_id ): array {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE seller_id = %d AND status = 'approved' ORDER BY id ASC", $seller_id ) ); // phpcs:ignore
		return $this->hydrate( (array) $rows );
	}
}
