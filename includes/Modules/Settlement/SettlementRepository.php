<?php
/**
 * Repository تسویه‌ها.
 *
 * @package Bespari\Modules\Settlement
 */

namespace Bespari\Modules\Settlement;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettlementRepository extends BaseRepository {

	protected string $table       = 'settlements';
	protected string $model_class = SettlementModel::class;

	public function get_by_id( int $id ): ?SettlementModel {
		$row = $this->find( $id );
		return $row ? SettlementModel::from_row( $row ) : null;
	}

	/**
	 * لیست صفحه‌بندی‌شده.
	 *
	 * @param array $filters channel_id, status, date_from, date_to
	 * @param int   $page
	 * @param int   $per_page
	 * @return array{items:SettlementModel[], total:int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = $this->table();
		$ch    = Helpers::table( 'channels' );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['channel_id'] ) ) {
			$where[]  = 's.channel_id = %d';
			$values[] = (int) $filters['channel_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 's.status = %s';
			$values[] = sanitize_key( $filters['status'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 's.period_start >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 's.period_end <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] );
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} s WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ); // phpcs:ignore

		$offset = max( 0, ( $page - 1 ) * $per_page );
		$sql    = "SELECT s.*, c.name AS channel_name FROM {$table} s LEFT JOIN {$ch} c ON c.id = s.channel_id WHERE {$where_sql} ORDER BY s.period_start DESC, s.id DESC LIMIT %d OFFSET %d";
		$all    = array_merge( $values, array( $per_page, $offset ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $all ) ); // phpcs:ignore

		$items = array();
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$items[] = SettlementModel::from_row( $r );
			}
		}
		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * سفارش‌های یک تسویه.
	 *
	 * @return array
	 */
	public function get_orders( int $settlement_id ): array {
		global $wpdb;
		$table = Helpers::table( 'orders' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE settlement_id = %d ORDER BY ordered_at ASC", $settlement_id ) ); // phpcs:ignore
		if ( ! $rows ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = \Bespari\Modules\Order\OrderModel::from_row( $r );
		}
		return $out;
	}
}
