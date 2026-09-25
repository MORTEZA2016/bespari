<?php
/**
 * سرویس صورت‌حساب‌های کانالی (بازطراحی تسویه).
 *
 * @package Bespari\Modules\ChannelInvoice
 */

namespace Bespari\Modules\ChannelInvoice;

use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChannelInvoiceService {

	private ChannelInvoiceRepository $repo;

	public function __construct() {
		$this->repo = new ChannelInvoiceRepository();
	}

	/**
	 * شماره صورت‌حساب یکتا — CINV-YYYYMMDD-XXXX.
	 */
	private function generate_invoice_number(): string {
		global $wpdb;
		$table  = Helpers::table( 'channel_invoices' );
		$prefix = 'CINV-' . gmdate( 'Ymd' ) . '-';

		$like = $wpdb->esc_like( $prefix ) . '%';
		$sql  = $wpdb->prepare( "SELECT invoice_number FROM {$table} WHERE invoice_number LIKE %s ORDER BY id DESC LIMIT 1", $like ); // phpcs:ignore
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
	 * ترکیب چند محموله یک کانال به یک صورت‌حساب.
	 *
	 * @param int    $channel_id   کانال.
	 * @param array  $batch_ids    محموله‌های انتخابی.
	 * @param int    $period_days  چند روزه بودن دوره صورت‌حساب.
	 * @param string $cash_due     تاریخ تسویه نقدی (Y-m-d).
	 * @param string $credit_due   تاریخ تسویه اعتباری (Y-m-d).
	 * @return int|WP_Error شناسه صورت‌حساب.
	 */
	public function create_from_batches( int $channel_id, array $batch_ids, int $period_days, string $cash_due, string $credit_due ): int|\WP_Error {
		global $wpdb;

		$channel_id = max( 0, $channel_id );
		$batch_ids  = array_values( array_unique( array_filter( array_map( 'intval', $batch_ids ) ) ) );
		$period_days = max( 0, $period_days );

		if ( 0 === $channel_id || empty( $batch_ids ) ) {
			return new \WP_Error( 'bespari_cinv_invalid', __( 'کانال یا محموله‌های انتخابی نامعتبر است.', 'bespari-core' ) );
		}

		$channel = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Helpers::table( 'channels' ) . ' WHERE id = %d', $channel_id ) ); // phpcs:ignore
		if ( ! $channel ) {
			return new \WP_Error( 'bespari_cinv_no_channel', __( 'کانال یافت نشد.', 'bespari-core' ) );
		}

		// حالت کانال باید صورت‌حساب به صورت‌حساب باشد.
		if ( 'statement' !== (string) $channel->settlement_mode ) {
			return new \WP_Error( 'bespari_cinv_mode', __( 'این کانال در حالت صورت‌حساب به صورت‌حساب نیست.', 'bespari-core' ) );
		}

		$batches_table = Helpers::table( 'shipment_batches' );
		$placeholders  = implode( ',', array_fill( 0, count( $batch_ids ), '%d' ) );
		$params        = array_merge( array( $channel_id ), $batch_ids );

		// گارد: محموله‌ها باید از همین کانال و بدون صورت‌حساب باشند.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$batches_table} WHERE channel_id = %d AND id IN ({$placeholders}) AND channel_invoice_id = 0", $params ) ); // phpcs:ignore

		if ( count( $rows ) !== count( $batch_ids ) ) {
			return new \WP_Error( 'bespari_cinv_batches_mismatch', __( 'بعضی محموله‌ها قابل ترکیب نیستند (شاید قبلاً در صورت‌حساب دیگری هستند).', 'bespari-core' ) );
		}

		$cash_total   = 0.0;
		$credit_total = 0.0;
		foreach ( $rows as $row ) {
			$cash_total   += Helpers::to_float( $row->cash_total );
			$credit_total += Helpers::to_float( $row->credit_total );
		}

		$now  = Helpers::now();
		$data = array(
			'invoice_number'      => $this->generate_invoice_number(),
			'channel_id'          => $channel_id,
			'period_days'         => $period_days,
			'status'              => 'settling',
			'cash_due_date'       => '' !== $cash_due ? $cash_due : null,
			'credit_due_date'     => '' !== $credit_due ? $credit_due : null,
			'cash_total'          => round( $cash_total, 2 ),
			'credit_total'        => round( $credit_total, 2 ),
			'total_batches'       => count( $rows ),
			'financials_snapshot' => wp_json_encode( array(
				'batches' => array_map( fn( $r ) => array(
					'batch_id'   => (int) $r->id,
					'batch_code' => (string) $r->batch_code,
					'cash_total' => Helpers::to_float( $r->cash_total ),
					'credit_total' => Helpers::to_float( $r->credit_total ),
				), $rows ),
			), JSON_UNESCAPED_UNICODE ),
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		$invoice_id = $this->repo->insert( $data );
		if ( ! $invoice_id ) {
			return new \WP_Error( 'bespari_cinv_insert_failed', __( 'ثبت صورت‌حساب ناموفق بود.', 'bespari-core' ) );
		}

		// اتصال محموله‌ها به صورت‌حساب (از لیست محموله‌ها حذف نمایش می‌شوند).
		foreach ( $batch_ids as $bid ) {
			$wpdb->update( $batches_table, array( 'channel_invoice_id' => $invoice_id, 'updated_at' => $now ), array( 'id' => $bid ) ); // phpcs:ignore
		}

		AuditLog::log( 'channel_invoice_created', 'channel_invoice', $invoice_id, null, array(
			'invoice_number' => $data['invoice_number'],
			'channel_id'     => $channel_id,
			'batches'        => $batch_ids,
			'period_days'    => $period_days,
		) );

		return $invoice_id;
	}

	/**
	 * ثبت تسویه نقدی (کد پیگیری اول) → تسویه گام اول.
	 */
	public function settle_cash( int $invoice_id, string $tracking_code ): bool|\WP_Error {
		$invoice = $this->repo->get_by_id( $invoice_id );
		if ( ! $invoice ) {
			return new \WP_Error( 'bespari_cinv_not_found', __( 'صورت‌حساب یافت نشد.', 'bespari-core' ) );
		}
		if ( 'settling' !== $invoice->status ) {
			return new \WP_Error( 'bespari_cinv_already', __( 'تسویه گام اول قبلاً ثبت شده است.', 'bespari-core' ) );
		}

		$this->repo->update( $invoice_id, array(
			'status'             => 'partial_settled',
			'cash_tracking_code' => sanitize_text_field( $tracking_code ),
			'cash_settled_at'    => Helpers::now(),
			'updated_at'         => Helpers::now(),
		) );

		AuditLog::log( 'channel_invoice_settled_cash', 'channel_invoice', $invoice_id, 'settling', 'partial_settled' );
		return true;
	}

	/**
	 * ثبت تسویه اعتباری (کد پیگیری دوم) → تسویه کامل + همگام‌سازی محموله‌ها و سفارش‌ها.
	 */
	public function settle_credit( int $invoice_id, string $tracking_code ): bool|\WP_Error {
		global $wpdb;
		$invoice = $this->repo->get_by_id( $invoice_id );
		if ( ! $invoice ) {
			return new \WP_Error( 'bespari_cinv_not_found', __( 'صورت‌حساب یافت نشد.', 'bespari-core' ) );
		}
		if ( ! in_array( $invoice->status, array( 'settling', 'partial_settled' ), true ) ) {
			return new \WP_Error( 'bespari_cinv_status', __( 'صورت‌حساب قبلاً تسویه کامل شده است.', 'bespari-core' ) );
		}

		$this->repo->update( $invoice_id, array(
			'status'               => 'settled',
			'credit_tracking_code' => sanitize_text_field( $tracking_code ),
			'credit_settled_at'    => Helpers::now(),
			'updated_at'           => Helpers::now(),
		) );

		// همگام‌سازی: سفارش‌های همه محموله‌های این صورت‌حساب → تسویه شده.
		$batches_table = Helpers::table( 'shipment_batches' );
		$orders        = Helpers::table( 'orders' );
		$now           = Helpers::now();

		$wpdb->query( $wpdb->prepare( "UPDATE {$orders} o JOIN {$batches_table} b ON o.batch_id = b.id SET o.settlement_status = 'settled', o.updated_at = %s WHERE b.channel_invoice_id = %d", $now, $invoice_id ) ); // phpcs:ignore

		AuditLog::log( 'channel_invoice_settled_credit', 'channel_invoice', $invoice_id, $invoice->status, 'settled' );
		return true;
	}

	/**
	 * نمای درختوار: صورت‌حساب → محموله‌ها → سفارش‌ها → آیتم‌ها.
	 *
	 * @return array{invoice: ChannelInvoiceModel, batches: array, orders: array}
	 */
	public function get_tree( int $invoice_id ): array {
		global $wpdb;
		$batches_table = Helpers::table( 'shipment_batches' );
		$orders        = Helpers::table( 'orders' );

		$invoice = $this->repo->get_by_id( $invoice_id );
		if ( ! $invoice ) {
			return array( 'invoice' => null, 'batches' => array(), 'orders' => array() );
		}

		$batches = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$batches_table} WHERE channel_invoice_id = %d ORDER BY id ASC", $invoice_id ) ); // phpcs:ignore

		$tree_orders = array();
		foreach ( (array) $batches as $batch ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, order_number, customer_name, sale_type, status, settlement_status, total_net, ordered_at FROM {$orders} WHERE batch_id = %d ORDER BY id ASC", (int) $batch->id ) ); // phpcs:ignore
			foreach ( (array) $rows as $row ) {
				$tree_orders[] = array(
					'batch_id'     => (int) $batch->id,
					'batch_code'   => (string) $batch->batch_code,
					'order_id'     => (int) $row->id,
					'order_number' => (string) $row->order_number,
					'customer_name' => (string) $row->customer_name,
					'sale_type'    => (string) $row->sale_type,
					'status'       => (string) $row->status,
					'settlement_status' => (string) $row->settlement_status,
					'total_net'    => Helpers::to_float( $row->total_net ),
					'ordered_at'   => (string) $row->ordered_at,
				);
			}
		}

		return array(
			'invoice' => $invoice,
			'batches' => (array) $batches,
			'orders'  => $tree_orders,
		);
	}

	/**
	 * بازمحاسبه مبالغ صورت‌حساب پس از مرجوعی (جمع از محموله‌های فعال).
	 */
	public function recalc_for_return( int $order_id ): void {
		global $wpdb;
		$batches_table = Helpers::table( 'shipment_batches' );
		$orders        = Helpers::table( 'orders' );

		$batch_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT batch_id FROM {$orders} WHERE id = %d", $order_id ) ); // phpcs:ignore
		if ( $batch_id <= 0 ) {
			return;
		}

		$invoice_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT channel_invoice_id FROM {$batches_table} WHERE id = %d", $batch_id ) ); // phpcs:ignore
		if ( $invoice_id <= 0 ) {
			return;
		}

		// جمع مجدد از همه محموله‌های صورت‌حساب.
		$batches = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$batches_table} WHERE channel_invoice_id = %d", $invoice_id ) ); // phpcs:ignore

		$cash_total   = 0.0;
		$credit_total = 0.0;
		foreach ( (array) $batches as $b ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT sale_type, financials_snapshot, total_net FROM {$orders} WHERE batch_id = %d", (int) $b->id ) ); // phpcs:ignore
			foreach ( (array) $rows as $row ) {
				$snapshot = json_decode( (string) $row->financials_snapshot, true );
				$net      = isset( $snapshot['net'] ) ? Helpers::to_float( $snapshot['net'] ) : Helpers::to_float( $row->total_net );
				if ( 'credit' === $row->sale_type ) {
					$credit_total += $net;
				} else {
					$cash_total += $net;
				}
			}
		}

		$this->repo->update( $invoice_id, array(
			'cash_total'   => round( $cash_total, 2 ),
			'credit_total' => round( $credit_total, 2 ),
			'updated_at'   => Helpers::now(),
		) );

		AuditLog::log( 'channel_invoice_recalc_return', 'channel_invoice', $invoice_id, null, array( 'order_id' => $order_id ) );
	}
}
