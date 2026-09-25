<?php
/**
 * ریپازیتوری صورتحساب‌ها.
 *
 * @package Bespari\Modules\Accounting
 */

namespace Bespari\Modules\Accounting;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InvoiceRepository {

	/**
	 * دریافت صورتحساب با آی‌دی.
	 */
	public function get_by_id( int $id ): ?InvoiceModel {
		global $wpdb;
		$orders = Helpers::table( 'orders' );
		$table  = Helpers::table( 'invoices' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT i.*, o.order_number AS order_number FROM {$table} i LEFT JOIN {$orders} o ON o.id = i.order_id WHERE i.id = %d", // phpcs:ignore
			$id
		), ARRAY_A );

		return $row ? InvoiceModel::from_row( $row ) : null;
	}

	/**
	 * صورتحساب یک سفارش (گارد دوبل).
	 */
	public function get_by_order( int $order_id ): ?InvoiceModel {
		global $wpdb;
		$table = Helpers::table( 'invoices' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE order_id = %d LIMIT 1",
			$order_id
		), ARRAY_A );

		return $row ? InvoiceModel::from_row( $row ) : null;
	}

	/**
	 * لیست صفحه‌بندی‌شده صورتحساب‌ها.
	 *
	 * @return array{items: array, total: int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table  = Helpers::table( 'invoices' );
		$orders = Helpers::table( 'orders' );

		$conditions = array( '1=1' );
		$values     = array();

		if ( ! empty( $filters['type'] ) ) {
			$conditions[] = 'i.type = %s';
			$values[]     = sanitize_key( $filters['type'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$conditions[] = 'i.created_at >= %s';
			$values[]     = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$conditions[] = 'i.created_at <= %s';
			$values[]     = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}
		if ( ! empty( $filters['search'] ) ) {
			$like         = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$conditions[] = '(i.invoice_number LIKE %s OR o.order_number LIKE %s)';
			$values[]     = $like;
			$values[]     = $like;
		}

		$where = implode( ' AND ', $conditions );

		$sql_total = "SELECT COUNT(*) FROM {$table} i LEFT JOIN {$orders} o ON o.id = i.order_id WHERE {$where}";
		$total     = (int) $wpdb->get_var( $values ? $wpdb->prepare( $sql_total, $values ) : $sql_total ); // phpcs:ignore

		$per_page = max( 1, $per_page );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;

		$sql_items = "SELECT i.*, o.order_number AS order_number FROM {$table} i LEFT JOIN {$orders} o ON o.id = i.order_id WHERE {$where} ORDER BY i.id DESC LIMIT %d OFFSET %d";
		$params    = array_merge( $values, array( $per_page, $offset ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql_items, $params ), ARRAY_A ); // phpcs:ignore

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = InvoiceModel::from_row( $row );
		}

		return array( 'items' => $items, 'total' => $total );
	}
}
