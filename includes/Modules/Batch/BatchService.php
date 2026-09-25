<?php
/**
 * سرویس محموله‌های کانالی (بازطراحی تسویه).
 *
 * @package Bespari\Modules\Batch
 */

namespace Bespari\Modules\Batch;

use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BatchService {

	private BatchRepository $repo;

	public function __construct() {
		$this->repo = new BatchRepository();
	}

	/**
	 * کد محموله یکتا — SHP-YYYYMMDD-XXXX.
	 */
	private function generate_batch_code(): string {
		global $wpdb;
		$table  = Helpers::table( 'shipment_batches' );
		$prefix = 'SHP-' . gmdate( 'Ymd' ) . '-';

		$like = $wpdb->esc_like( $prefix ) . '%';
		$sql  = $wpdb->prepare( "SELECT batch_code FROM {$table} WHERE batch_code LIKE %s ORDER BY id DESC LIMIT 1", $like ); // phpcs:ignore
		$last = $wpdb->get_var( $sql ); // phpcs:ignore

		$seq = 1;
		if ( $last ) {
			$suffix = substr( (string) $last, strlen( $prefix ) );
			if ( is_numeric( $suffix ) ) {
				$seq = (int) $suffix + 1;
			}
		}

		return $prefix . str_pad( (string) $seq, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * ساخت محموله از چند سفارش یک کانال.
	 *
	 * @param int   $channel_id شناسه کانال.
	 * @param array $order_ids  سفارش‌های انتخابی.
	 * @return int|WP_Error شناسه محموله.
	 */
	public function create_from_orders( int $channel_id, array $order_ids ): int|\WP_Error {
		global $wpdb;

		$channel_id = max( 0, $channel_id );
		$order_ids  = array_values( array_unique( array_filter( array_map( 'intval', $order_ids ) ) ) );

		if ( 0 === $channel_id || empty( $order_ids ) ) {
			return new \WP_Error( 'bespari_batch_invalid', __( 'کانال یا سفارش‌های انتخابی نامعتبر است.', 'bespari-core' ) );
		}

		$channel = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Helpers::table( 'channels' ) . ' WHERE id = %d', $channel_id ) ); // phpcs:ignore
		if ( ! $channel ) {
			return new \WP_Error( 'bespari_batch_no_channel', __( 'کانال یافت نشد.', 'bespari-core' ) );
		}

		$orders_table = Helpers::table( 'orders' );
		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$params       = array_merge( array( $channel_id ), $order_ids );

		// گارد: همه سفارشات باید از همین کانال، بدون محموله و بدون صورت‌حساب باشند.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE channel_id = %d AND id IN ({$placeholders}) AND batch_id = 0 AND channel_invoice_id = 0 AND settlement_status = 'pending'", $params ) ); // phpcs:ignore

		if ( count( $rows ) !== count( $order_ids ) ) {
			return new \WP_Error( 'bespari_batch_orders_mismatch', __( 'بعضی سفارش‌ها قابل تبدیل نیستند (شاید قبلاً در محموله یا صورت‌حساب دیگری هستند).', 'bespari-core' ) );
		}

		$cash_total   = 0.0;
		$credit_total = 0.0;
		$sum_financials = array();
		foreach ( $rows as $row ) {
			$snapshot = json_decode( (string) $row->financials_snapshot, true );
			$net      = isset( $snapshot['net'] ) ? Helpers::to_float( $snapshot['net'] ) : Helpers::to_float( $row->total_net );
			if ( 'credit' === $row->sale_type ) {
				$credit_total += $net;
			} else {
				$cash_total += $net;
			}
			$sum_financials[] = array(
				'order_id' => (int) $row->id,
				'order_number' => (string) $row->order_number,
				'sale_type' => (string) $row->sale_type,
				'net'      => $net,
			);
		}

		$now  = Helpers::now();
		$data = array(
			'batch_code'          => $this->generate_batch_code(),
			'channel_id'          => $channel_id,
			'settlement_mode'     => sanitize_key( (string) $channel->settlement_mode ),
			'status'              => 'pending',
			'cash_total'          => round( $cash_total, 2 ),
			'credit_total'        => round( $credit_total, 2 ),
			'total_orders'        => count( $rows ),
			'financials_snapshot' => wp_json_encode( $sum_financials, JSON_UNESCAPED_UNICODE ),
			'channel_invoice_id'  => 0,
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		$batch_id = $this->repo->insert( $data );
		if ( ! $batch_id ) {
			return new \WP_Error( 'bespari_batch_insert_failed', __( 'ثبت محموله ناموفق بود.', 'bespari-core' ) );
		}

		// اتصال سفارش‌ها به محموله.
		foreach ( $order_ids as $oid ) {
			$wpdb->update( $orders_table, array( 'batch_id' => $batch_id, 'updated_at' => $now ), array( 'id' => $oid ) ); // phpcs:ignore
		}

		AuditLog::log( 'batch_created', 'shipment_batch', $batch_id, null, array(
			'batch_code' => $data['batch_code'],
			'channel_id' => $channel_id,
			'orders'     => $order_ids,
		) );

		return $batch_id;
	}

	/**
	 * ثبت ارسال محموله.
	 */
	public function ship( int $batch_id ): bool|\WP_Error {
		$batch = $this->repo->get_by_id( $batch_id );
		if ( ! $batch ) {
			return new \WP_Error( 'bespari_batch_not_found', __( 'محموله یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' !== $batch->status ) {
			return new \WP_Error( 'bespari_batch_status', __( 'فقط محموله در انتظار ارسال قابل ارسال است.', 'bespari-core' ) );
		}

		$this->repo->update( $batch_id, array(
			'status'     => 'shipped',
			'shipped_at' => Helpers::now(),
			'updated_at' => Helpers::now(),
		) );

		// همگام‌سازی وضعیت سفارش‌های داخل محموله → ارسال شده.
		$this->sync_orders_status( $batch_id, 'shipped' );

		AuditLog::log( 'batch_shipped', 'shipment_batch', $batch_id, 'pending', 'shipped' );
		return true;
	}

	/**
	 * ثبت تسویه بخش نقدی (کد پیگیری اول).
	 */
	public function settle_cash( int $batch_id, string $tracking_code ): bool|\WP_Error {
		$batch = $this->repo->get_by_id( $batch_id );
		if ( ! $batch ) {
			return new \WP_Error( 'bespari_batch_not_found', __( 'محموله یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' === $batch->status ) {
			return new \WP_Error( 'bespari_batch_not_shipped', __( 'ابتدا محموله را ارسال کنید.', 'bespari-core' ) );
		}
		if ( 'settled' === $batch->status || 'partial_settled' === $batch->status ) {
			return new \WP_Error( 'bespari_batch_already', __( 'تسویه بخش نقدی قبلاً ثبت شده است.', 'bespari-core' ) );
		}

		$this->repo->update( $batch_id, array(
			'status'             => 'partial_settled',
			'cash_tracking_code' => sanitize_text_field( $tracking_code ),
			'cash_settled_at'    => Helpers::now(),
			'updated_at'         => Helpers::now(),
		) );

		AuditLog::log( 'batch_settled_cash', 'shipment_batch', $batch_id, 'shipped', 'partial_settled' );
		return true;
	}

	/**
	 * ثبت تسویه بخش اعتباری (کد پیگیری دوم) → تسویه کامل.
	 */
	public function settle_credit( int $batch_id, string $tracking_code ): bool|\WP_Error {
		$batch = $this->repo->get_by_id( $batch_id );
		if ( ! $batch ) {
			return new \WP_Error( 'bespari_batch_not_found', __( 'محموله یافت نشد.', 'bespari-core' ) );
		}
		if ( ! in_array( $batch->status, array( 'partial_settled', 'shipped' ), true ) ) {
			return new \WP_Error( 'bespari_batch_status', __( 'برای تسویه کامل، ابتدا وضعیت محموله باید ارسال/تسویه بخش اول باشد.', 'bespari-core' ) );
		}

		$this->repo->update( $batch_id, array(
			'status'               => 'settled',
			'credit_tracking_code' => sanitize_text_field( $tracking_code ),
			'credit_settled_at'    => Helpers::now(),
			'updated_at'           => Helpers::now(),
		) );

		// سفارش‌های داخل محموله → تسویه شده.
		$this->sync_orders_settlement( $batch_id, 'settled' );

		AuditLog::log( 'batch_settled_credit', 'shipment_batch', $batch_id, $batch->status, 'settled' );
		return true;
	}

	/**
	 * همگام‌سازی status سفارش‌های محموله.
	 */
	private function sync_orders_status( int $batch_id, string $status ): void {
		global $wpdb;
		$orders = Helpers::table( 'orders' );

		$wpdb->query( $wpdb->prepare( "UPDATE {$orders} SET status = %s, updated_at = %s WHERE batch_id = %d", sanitize_key( $status ), Helpers::now(), $batch_id ) ); // phpcs:ignore
	}

	/**
	 * همگام‌سازی settlement_status سفارش‌های محموله.
	 */
	private function sync_orders_settlement( int $batch_id, string $settlement_status ): void {
		global $wpdb;
		$orders = Helpers::table( 'orders' );

		$wpdb->query( $wpdb->prepare( "UPDATE {$orders} SET settlement_status = %s, updated_at = %s WHERE batch_id = %d", sanitize_key( $settlement_status ), Helpers::now(), $batch_id ) ); // phpcs:ignore
	}

	/**
	 * بازمحاسبه مبالغ محموله پس از مرجوعی یک سفارش.
	 */
	public function recalc_for_return( int $order_id ): void {
		global $wpdb;
		$orders = Helpers::table( 'orders' );

		$batch_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT batch_id FROM {$orders} WHERE id = %d", $order_id ) ); // phpcs:ignore
		if ( $batch_id <= 0 ) {
			return;
		}

		$batch = $this->repo->get_by_id( $batch_id );
		if ( ! $batch ) {
			return;
		}

		// جمع مجدد از سفارش‌های فعلی محموله.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT sale_type, financials_snapshot, total_net FROM {$orders} WHERE batch_id = %d", $batch_id ) ); // phpcs:ignore

		$cash_total   = 0.0;
		$credit_total = 0.0;
		foreach ( (array) $rows as $row ) {
			$snapshot = json_decode( (string) $row->financials_snapshot, true );
			$net      = isset( $snapshot['net'] ) ? Helpers::to_float( $snapshot['net'] ) : Helpers::to_float( $row->total_net );
			if ( 'credit' === $row->sale_type ) {
				$credit_total += $net;
			} else {
				$cash_total += $net;
			}
		}

		$this->repo->update( $batch_id, array(
			'cash_total'   => round( $cash_total, 2 ),
			'credit_total' => round( $credit_total, 2 ),
			'total_orders' => count( (array) $rows ),
			'updated_at'   => Helpers::now(),
		) );

		AuditLog::log( 'batch_recalc_return', 'shipment_batch', $batch_id, null, array( 'order_id' => $order_id, 'cash_total' => $cash_total, 'credit_total' => $credit_total ) );
	}

	/**
	 * شمارش سفارش‌های محموله.
	 */
	public function count_orders( int $batch_id ): int {
		global $wpdb;
		$orders = Helpers::table( 'orders' );

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE batch_id = %d", $batch_id ) ); // phpcs:ignore
	}

	/**
	 * حذف محموله — فقط pending و با دسترسی مدیریت.
	 */
	public function delete( int $batch_id ): bool|\WP_Error {
		global $wpdb;
		$batch = $this->repo->get_by_id( $batch_id );
		if ( ! $batch ) {
			return new \WP_Error( 'bespari_batch_not_found', __( 'محموله یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' !== $batch->status ) {
			return new \WP_Error( 'bespari_batch_delete_locked', __( 'محموله ارسال/تسویه‌شده قابل حذف نیست.', 'bespari-core' ) );
		}

		// سفارش‌ها به لیست سفارشات برمی‌گردند.
		$wpdb->update( Helpers::table( 'orders' ), array( 'batch_id' => 0, 'updated_at' => Helpers::now() ), array( 'batch_id' => $batch_id ) ); // phpcs:ignore

		$this->repo->delete( $batch_id );
		AuditLog::log( 'batch_deleted', 'shipment_batch', $batch_id, $batch->to_array(), null );
		return true;
	}
}
