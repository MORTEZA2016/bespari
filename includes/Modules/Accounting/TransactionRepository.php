<?php
/**
 * ریپازیتوری تراکنش‌های کیف پول.
 *
 * @package Bespari\Modules\Accounting
 */

namespace Bespari\Modules\Accounting;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TransactionRepository {

	/**
	 * دریافت یک تراکنش با آی‌دی.
	 */
	public function get_by_id( int $id ): ?TransactionModel {
		global $wpdb;
		$table = Helpers::table( 'transactions' );
		$users = $GLOBALS['wpdb']->users;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT t.*, u.display_name AS seller_name FROM {$table} t LEFT JOIN {$users} u ON u.ID = t.seller_id WHERE t.id = %d", // phpcs:ignore
			$id
		), ARRAY_A );

		return $row ? TransactionModel::from_row( $row ) : null;
	}

	/**
	 * جمع مانده کیف پول یک بازاریاب (از SUM، نه balance_after).
	 */
	public function get_balance( int $seller_id ): float {
		global $wpdb;
		$table = Helpers::table( 'transactions' );

		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE seller_id = %d",
			$seller_id
		) );
	}

	/**
	 * مانده همه بازاریاب‌ها (SUM per seller).
	 *
	 * @return array آرایه‌ای از stdClass: seller_id, seller_name, balance
	 */
	public function get_all_seller_balances(): array {
		global $wpdb;
		$table = Helpers::table( 'transactions' );
		$users = $GLOBALS['wpdb']->users;

		return $wpdb->get_results( "
			SELECT t.seller_id, u.display_name AS seller_name, COALESCE(SUM(t.amount), 0) AS balance
			FROM {$table} t
			LEFT JOIN {$users} u ON u.ID = t.seller_id
			GROUP BY t.seller_id, u.display_name
			ORDER BY balance DESC
		" ); // phpcs:ignore
	}

	/**
	 * وجود تراکنش برای یک مرجع (گارد دوبل).
	 */
	public function exists_for_ref( string $ref_type, int $ref_id ): bool {
		global $wpdb;
		$table = Helpers::table( 'transactions' );

		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE ref_type = %s AND ref_id = %d LIMIT 1",
			$ref_type,
			$ref_id
		) );
	}

	/**
	 * لیست صفحه‌بندی‌شده تراکنش‌ها.
	 *
	 * @return array{items: array, total: int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = Helpers::table( 'transactions' );
		$users = $GLOBALS['wpdb']->users;

		$conditions = array( '1=1' );
		$values     = array();

		if ( ! empty( $filters['seller_id'] ) ) {
			$conditions[] = 't.seller_id = %d';
			$values[]     = (int) $filters['seller_id'];
		}
		if ( ! empty( $filters['type'] ) ) {
			$conditions[] = 't.type = %s';
			$values[]     = sanitize_key( $filters['type'] );
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$conditions[] = 't.created_at >= %s';
			$values[]     = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$conditions[] = 't.created_at <= %s';
			$values[]     = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}
		if ( ! empty( $filters['search'] ) ) {
			$like         = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$conditions[] = '(t.description LIKE %s OR u.display_name LIKE %s)';
			$values[]     = $like;
			$values[]     = $like;
		}

		$where = implode( ' AND ', $conditions );

		$sql_total = "SELECT COUNT(*) FROM {$table} t LEFT JOIN {$users} u ON u.ID = t.seller_id WHERE {$where}";
		$total     = (int) $wpdb->get_var( $values ? $wpdb->prepare( $sql_total, $values ) : $sql_total ); // phpcs:ignore

		$per_page = max( 1, $per_page );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;

		$sql_items = "SELECT t.*, u.display_name AS seller_name FROM {$table} t LEFT JOIN {$users} u ON u.ID = t.seller_id WHERE {$where} ORDER BY t.id DESC LIMIT %d OFFSET %d";
		$params    = array_merge( $values, array( $per_page, $offset ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql_items, $params ), ARRAY_A ); // phpcs:ignore

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = TransactionModel::from_row( $row );
		}

		return array( 'items' => $items, 'total' => $total );
	}
}
