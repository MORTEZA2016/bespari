<?php
/**
 * سرویس حسابداری — کیف پول بازاریاب + صورتحساب.
 *
 * @package Bespari\Modules\Accounting
 */

namespace Bespari\Modules\Accounting;

use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AccountingService {

	private TransactionRepository $txn_repo;
	private InvoiceRepository $inv_repo;

	public function __construct() {
		$this->txn_repo = new TransactionRepository();
		$this->inv_repo = new InvoiceRepository();
	}

	/**
	 * مانده کیف پول بازاریاب (SUM).
	 */
	public function get_balance( int $seller_id ): float {
		return $this->txn_repo->get_balance( $seller_id );
	}

	/**
	 * ثبت تراکنش کیف پول.
	 *
	 * @param int    $seller_id   بازاریاب.
	 * @param string $type        charge/commission/payout/adjust/refund.
	 * @param float  $amount      علامت‌دار (+ ورودی، - خروجی).
	 * @param string $ref_type    مرجع (payout/commission/manual/return).
	 * @param int    $ref_id      آی‌دی مرجع.
	 * @param string $description توضیح.
	 * @return int|\WP_Error
	 */
	public function record( int $seller_id, string $type, float $amount, string $ref_type = '', int $ref_id = 0, string $description = '' ) {
		global $wpdb;

		if ( $seller_id <= 0 ) {
			return new \WP_Error( 'invalid_seller', __( 'بازاریاب نامعتبر است.', 'bespari-core' ) );
		}
		if ( abs( $amount ) < 0.001 ) {
			return new \WP_Error( 'invalid_amount', __( 'مبلغ تراکنش نمی‌تواند صفر باشد.', 'bespari-core' ) );
		}

		$allowed = array( 'charge', 'commission', 'payout', 'adjust', 'refund' );
		if ( ! in_array( $type, $allowed, true ) ) {
			$type = 'adjust';
		}

		// گارد دوبل برای تراکنش‌های خودکار (با ref).
		if ( '' !== $ref_type && $ref_id > 0 && $this->txn_repo->exists_for_ref( $ref_type, $ref_id ) ) {
			return 0;
		}

		$balance_after = $this->get_balance( $seller_id ) + $amount;

		$id = $this->txn_repo->insert( array(
			'seller_id'     => $seller_id,
			'type'          => $type,
			'amount'        => round( $amount, 2 ),
			'balance_after' => round( $balance_after, 2 ),
			'ref_type'      => sanitize_key( $ref_type ),
			'ref_id'        => $ref_id,
			'description'   => sanitize_text_field( $description ),
			'user_id'       => get_current_user_id(),
			'created_at'    => Helpers::now(),
		) );

		if ( ! $id ) {
			return new \WP_Error( 'insert_failed', __( 'خطا در ثبت تراکنش.', 'bespari-core' ) );
		}

		AuditLog::created( 'transaction', $id, array(
			'seller_id' => $seller_id,
			'type'      => $type,
			'amount'    => $amount,
			'ref_type'  => $ref_type,
			'ref_id'    => $ref_id,
		) );

		return $id;
	}

	/**
	 * لیست تراکنش‌ها با فیلتر.
	 */
	public function list_transactions( array $args = array() ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters  = array_intersect_key( $args, array_flip( array( 'seller_id', 'type', 'date_from', 'date_to', 'search' ) ) );
		return $this->txn_repo->paginate( $filters, $page, $per_page );
	}

	public function get_transaction( int $id ): ?TransactionModel {
		return $this->txn_repo->get_by_id( $id );
	}

	/**
	 * مانده همه بازاریاب‌ها.
	 */
	public function get_all_seller_balances(): array {
		return (array) $this->txn_repo->get_all_seller_balances();
	}

	/**
	 * تولید صورتحساب برای سفارش (از snapshot فریز شده).
	 *
	 * @return int|\WP_Error
	 */
	public function generate_invoice( int $order_id ) {
		global $wpdb;

		if ( $order_id <= 0 ) {
			return new \WP_Error( 'invalid_order', __( 'سفارش نامعتبر است.', 'bespari-core' ) );
		}

		// گارد دوبل: هر سفارش حداکثر یک صورتحساب.
		if ( $this->inv_repo->get_by_order( $order_id ) ) {
			return new \WP_Error( 'duplicate', __( 'برای این سفارش قبلاً صورتحساب صادر شده است.', 'bespari-core' ) );
		}

		$o_table = Helpers::table( 'orders' );
		$order   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$o_table} WHERE id = %d", $order_id ) ); // phpcs:ignore

		if ( ! $order ) {
			return new \WP_Error( 'not_found', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}

		// خطوط از آیتم‌های سفارش (snapshot فریز در order_items).
		$items_table = Helpers::table( 'order_items' );
		$items       = $wpdb->get_results( $wpdb->prepare(
			"SELECT product_name, sku, quantity, unit_price, line_total FROM {$items_table} WHERE order_id = %d", // phpcs:ignore
			$order_id
		) );

		if ( empty( $items ) ) {
			return new \WP_Error( 'no_items', __( 'سفارش آیتمی ندارد؛ امکان صدور صورتحساب نیست.', 'bespari-core' ) );
		}

		$lines = array();
		$total = 0.0;
		foreach ( $items as $item ) {
			$line_total = (float) $item->line_total;
			$total     += $line_total;
			$lines[]    = array(
				'product_name' => (string) $item->product_name,
				'sku'          => (string) $item->sku,
				'quantity'     => (int) $item->quantity,
				'unit_price'   => (float) $item->unit_price,
				'line_total'   => $line_total,
			);
		}

		$meta = array(
			'order_number' => (string) $order->order_number,
			'customer'     => (string) $order->customer_name,
			'ordered_at'   => (string) $order->ordered_at,
			'lines'        => $lines,
		);

		$invoice_number = $this->generate_invoice_number();

		$id = $this->inv_repo->insert( array(
			'invoice_number' => $invoice_number,
			'order_id'       => $order_id,
			'type'           => 'sale',
			'total'          => round( $total, 2 ),
			'meta'           => wp_json_encode( $meta, JSON_UNESCAPED_UNICODE ),
			'created_at'     => Helpers::now(),
		) );

		if ( ! $id ) {
			return new \WP_Error( 'insert_failed', __( 'خطا در صدور صورتحساب.', 'bespari-core' ) );
		}

		AuditLog::created( 'invoice', $id, array(
			'invoice_number' => $invoice_number,
			'order_id'       => $order_id,
			'total'          => $total,
		) );

		return $id;
	}

	public function get_invoice( int $id ): ?InvoiceModel {
		return $this->inv_repo->get_by_id( $id );
	}

	public function list_invoices( array $args = array() ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters  = array_intersect_key( $args, array_flip( array( 'type', 'date_from', 'date_to', 'search' ) ) );
		return $this->inv_repo->paginate( $filters, $page, $per_page );
	}

	/**
	 * شماره صورتحساب یکتا: INV-YYYYMMDD-XXXX.
	 */
	private function generate_invoice_number(): string {
		global $wpdb;
		$table = Helpers::table( 'invoices' );

		$prefix = 'INV-' . gmdate( 'Ymd' ) . '-';

		for ( $i = 0; $i < 20; $i++ ) {
			$attempt = $prefix . str_pad( (string) wp_rand( 1, 9999 ), 4, '0', STR_PAD_LEFT );
			$exists  = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE invoice_number = %s", $attempt ) ); // phpcs:ignore
			if ( ! $exists ) {
				return $attempt;
			}
		}

		return $prefix . str_pad( (string) time(), 10, '0', STR_PAD_LEFT );
	}
}
