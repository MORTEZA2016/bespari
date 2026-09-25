<?php
/**
 * ریپازیتوری صورت‌حساب‌های کانالی.
 *
 * @package Bespari\Modules\ChannelInvoice
 */

namespace Bespari\Modules\ChannelInvoice;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChannelInvoiceRepository {

	/**
	 * یافتن یک صورت‌حساب با شناسه.
	 */
	public function get_by_id( int $id ): ?ChannelInvoiceModel {
		global $wpdb;
		$table = Helpers::table( 'channel_invoices' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore
		return $row ? ChannelInvoiceModel::from_row( $row ) : null;
	}

	/**
	 * لیست صورت‌حساب‌ها با فیلتر.
	 *
	 * @param array $filters channel_id, status, search, date_from, date_to.
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = Helpers::table( 'channel_invoices' );
		$ch    = Helpers::table( 'channels' );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['channel_id'] ) ) {
			$where[]  = 'i.channel_id = %d';
			$params[] = (int) $filters['channel_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'i.status = %s';
			$params[] = sanitize_key( (string) $filters['status'] );
		}
		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( (string) $filters['search'] ) ) . '%';
			$where[]  = '(i.invoice_number LIKE %s OR c.name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'i.created_at >= %s';
			$params[] = sanitize_text_field( (string) $filters['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'i.created_at <= %s';
			$params[] = sanitize_text_field( (string) $filters['date_to'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = max( 0, ( $page - 1 ) * $per_page );

		$sql_total = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} i LEFT JOIN {$ch} c ON c.id = i.channel_id WHERE {$where_sql}", $params ); // phpcs:ignore
		$total     = (int) $wpdb->get_var( $sql_total ); // phpcs:ignore

		$params[] = $offset;
		$params[] = $per_page;
		$sql      = $wpdb->prepare( "SELECT i.*, c.name AS channel_name FROM {$table} i LEFT JOIN {$ch} c ON c.id = i.channel_id WHERE {$where_sql} ORDER BY i.id DESC LIMIT %d, %d", $params ); // phpcs:ignore
		$rows     = $wpdb->get_results( $sql ); // phpcs:ignore

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = ChannelInvoiceModel::from_row( $row );
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * درج صورت‌حساب جدید.
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = Helpers::table( 'channel_invoices' );

		$wpdb->insert( $table, $data ); // phpcs:ignore
		return (int) $wpdb->insert_id;
	}

	/**
	 * به‌روزرسانی صورت‌حساب.
	 */
	public function update( int $id, array $data ): void {
		global $wpdb;
		$table = Helpers::table( 'channel_invoices' );

		$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore
	}
}
