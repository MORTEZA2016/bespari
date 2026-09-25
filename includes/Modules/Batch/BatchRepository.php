<?php
/**
 * ریپازیتوری محموله‌های کانالی.
 *
 * @package Bespari\Modules\Batch
 */

namespace Bespari\Modules\Batch;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BatchRepository {

	/**
	 * یافتن یک محموله با شناسه.
	 */
	public function get_by_id( int $id ): ?BatchModel {
		global $wpdb;
		$table = Helpers::table( 'shipment_batches' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore
		return $row ? BatchModel::from_row( $row ) : null;
	}

	/**
	 * یافتن با کد محموله.
	 */
	public function find_by_code( string $code ): ?BatchModel {
		global $wpdb;
		$table = Helpers::table( 'shipment_batches' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE batch_code = %s", $code ) ); // phpcs:ignore
		return $row ? BatchModel::from_row( $row ) : null;
	}

	/**
	 * لیست محموله‌ها با فیلتر.
	 *
	 * @param array $filters channel_id, status, invoice_id (0 = فقط بدون صورت‌حساب), search, date_from, date_to.
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		$table = Helpers::table( 'shipment_batches' );
		$ch    = Helpers::table( 'channels' );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['channel_id'] ) ) {
			$where[]  = 'b.channel_id = %d';
			$params[] = (int) $filters['channel_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'b.status = %s';
			$params[] = sanitize_key( (string) $filters['status'] );
		}
		// invoice_id = 0 یعنی فقط محموله‌های بدون صورت‌حساب؛ > 0 یعنی محموله‌های یک صورت‌حساب.
		if ( isset( $filters['invoice_id'] ) ) {
			$where[]  = 'b.channel_invoice_id = %d';
			$params[] = (int) $filters['invoice_id'];
		}
		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( (string) $filters['search'] ) ) . '%';
			$where[]  = '(b.batch_code LIKE %s OR c.name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'b.created_at >= %s';
			$params[] = sanitize_text_field( (string) $filters['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'b.created_at <= %s';
			$params[] = sanitize_text_field( (string) $filters['date_to'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = max( 0, ( $page - 1 ) * $per_page );

		$sql_total = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} b LEFT JOIN {$ch} c ON c.id = b.channel_id WHERE {$where_sql}", $params ); // phpcs:ignore
		$total     = (int) $wpdb->get_var( $sql_total ); // phpcs:ignore

		$params[] = $offset;
		$params[] = $per_page;
		$sql      = $wpdb->prepare( "SELECT b.*, c.name AS channel_name FROM {$table} b LEFT JOIN {$ch} c ON c.id = b.channel_id WHERE {$where_sql} ORDER BY b.id DESC LIMIT %d, %d", $params ); // phpcs:ignore
		$rows     = $wpdb->get_results( $sql ); // phpcs:ignore

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = BatchModel::from_row( $row );
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * درج محموله جدید.
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = Helpers::table( 'shipment_batches' );

		$wpdb->insert( $table, $data ); // phpcs:ignore
		return (int) $wpdb->insert_id;
	}

	/**
	 * به‌روزرسانی محموله.
	 */
	public function update( int $id, array $data ): void {
		global $wpdb;
		$table = Helpers::table( 'shipment_batches' );

		$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore
	}

	/**
	 * حذف محموله.
	 */
	public function delete( int $id ): void {
		global $wpdb;
		$table = Helpers::table( 'shipment_batches' );

		$wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore
	}
}
