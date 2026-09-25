<?php
/**
 * Repository سفارشات.
 *
 * @package Bespari\Modules\Order
 */

namespace Bespari\Modules\Order;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderRepository extends BaseRepository {

	protected string $table       = 'orders';
	protected string $model_class = OrderModel::class;

	public function get_by_id( int $id ): ?OrderModel {
		$row = $this->find( $id );
		return $row ? OrderModel::from_row( $row ) : null;
	}

	public function get_by_order_number( string $number ): ?OrderModel {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_number = %s LIMIT 1", $number ) ); // phpcs:ignore
		return $row ? OrderModel::from_row( $row ) : null;
	}

	/**
	 * سفارش کامل با آیتم‌ها.
	 */
	public function get_full( int $id ): ?OrderModel {
		$order = $this->get_by_id( $id );
		if ( ! $order ) {
			return null;
		}
		$order->items = $this->get_items( $id );
		return $order;
	}

	/**
	 * آیتم‌های یک سفارش.
	 *
	 * @return array
	 */
	public function get_items( int $order_id ): array {
		global $wpdb;
		$table = Helpers::table( 'order_items' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC", $order_id ) ); // phpcs:ignore
		if ( ! $rows ) {
			return array();
		}
		foreach ( $rows as &$r ) {
			if ( ! empty( $r->financials ) && is_string( $r->financials ) ) {
				$r->financials = json_decode( $r->financials, true );
			}
		}
		return $rows;
	}

	/**
	 * ذخیره آیتم‌ها (جایگزینی کامل).
	 *
	 * @param int   $order_id آی‌دی سفارش.
	 * @param array $items    breakdown از Calculator.
	 */
	public function save_items( int $order_id, array $items ): void {
		global $wpdb;
		$table = Helpers::table( 'order_items' );
		$wpdb->delete( $table, array( 'order_id' => $order_id ) );
		$now = Helpers::now();
		foreach ( $items as $it ) {
			$wpdb->insert( $table, array(
				'order_id'     => $order_id,
				'product_id'   => (int) ( $it['product_id'] ?? 0 ),
				'product_name' => sanitize_text_field( $it['product_name'] ?? '' ),
				'sku'          => sanitize_text_field( $it['sku'] ?? '' ),
				'quantity'     => (int) ( $it['quantity'] ?? 1 ),
				'base_price'   => (float) ( $it['base_price'] ?? 0 ),
				'unit_price'   => (float) ( $it['unit_price'] ?? 0 ),
				'line_total'   => (float) ( $it['line_total'] ?? 0 ),
				'cost'         => (float) ( $it['cost'] ?? 0 ),
				'financials'   => ! empty( $it['financials'] ) ? wp_json_encode( $it['financials'], JSON_UNESCAPED_UNICODE ) : null,
				'created_at'   => $now,
			) );
		}
	}

	/**
	 * لیست صفحه‌بندی‌شده با فیلترهای چندسطحی.
	 *
	 * @param array $filters search, channel_id, brand_id, seller_id, status, payment_status, settlement_status, sale_type, date_from, date_to
	 * @param int   $page
	 * @param int   $per_page
	 * @return array{items:OrderModel[], total:int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = $this->table();
		$ch    = Helpers::table( 'channels' );
		$br    = Helpers::table( 'brands' );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $filters['search'] ) ) {
			$where[]  = '(o.order_number LIKE %s OR o.customer_name LIKE %s OR o.customer_phone LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}
		if ( ! empty( $filters['channel_id'] ) ) {
			$where[]  = 'o.channel_id = %d';
			$values[] = (int) $filters['channel_id'];
		}
		if ( ! empty( $filters['brand_id'] ) ) {
			$where[]  = 'o.brand_id = %d';
			$values[] = (int) $filters['brand_id'];
		}
		if ( ! empty( $filters['seller_id'] ) ) {
			$where[]  = 'o.seller_id = %d';
			$values[] = (int) $filters['seller_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'o.status = %s';
			$values[] = sanitize_key( $filters['status'] );
		}
		if ( ! empty( $filters['payment_status'] ) ) {
			$where[]  = 'o.payment_status = %s';
			$values[] = sanitize_key( $filters['payment_status'] );
		}
		if ( ! empty( $filters['settlement_status'] ) ) {
			$where[]  = 'o.settlement_status = %s';
			$values[] = sanitize_key( $filters['settlement_status'] );
		}
		if ( ! empty( $filters['sale_type'] ) ) {
			$where[]  = 'o.sale_type = %s';
			$values[] = sanitize_key( $filters['sale_type'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'o.ordered_at >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'o.ordered_at <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} o WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ); // phpcs:ignore

		$offset = max( 0, ( $page - 1 ) * $per_page );

		$sql  = "SELECT o.*, c.name AS channel_name, b.name AS brand_name";
		$sql .= " FROM {$table} o";
		$sql .= " LEFT JOIN {$ch} c ON c.id = o.channel_id";
		$sql .= " LEFT JOIN {$br} b ON b.id = o.brand_id";
		$sql .= " WHERE {$where_sql} ORDER BY o.ordered_at DESC, o.id DESC LIMIT %d OFFSET %d";

		$all_values   = array_merge( $values, array( $per_page, $offset ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( $sql, $all_values ) ); // phpcs:ignore

		$items = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$items[] = OrderModel::from_row( $row );
			}
		}

		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * سفارش‌های تسویه‌نشده یک کانال در بازه (برای Settlement::generate).
	 *
	 * @return OrderModel[]
	 */
	public function get_unsettled_by_channel_period( int $channel_id, string $start, string $end ): array {
		global $wpdb;
		$table = $this->table();
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE channel_id = %d AND settlement_status = 'pending' AND ordered_at >= %s AND ordered_at <= %s ORDER BY ordered_at ASC", // phpcs:ignore
			$channel_id,
			$start . ' 00:00:00',
			$end . ' 23:59:59'
		) );
		if ( ! $rows ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			$out[] = OrderModel::from_row( $r );
		}
		return $out;
	}
}
