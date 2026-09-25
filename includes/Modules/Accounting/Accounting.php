<?php
/**
 * ماژول حسابداری — ثبت هوک‌های ادمین.
 *
 * @package Bespari\Modules\Accounting
 */

namespace Bespari\Modules\Accounting;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Accounting {

	/**
	 * ثبت هوک‌ها.
	 */
	public function register(): void {
		// تراکنش‌های خودکار کیف پول.
		add_action( 'bespari_commission_approved', array( $this, 'on_commission_approved' ), 10, 3 );
		add_action( 'bespari_payout_paid', array( $this, 'on_payout_paid' ), 10, 3 );

		// اکشن‌های ادمین.
		add_action( 'admin_post_bespari_record_transaction', array( $this, 'handle_record_transaction' ) );
		add_action( 'admin_post_bespari_generate_invoice', array( $this, 'handle_generate_invoice' ) );
	}

	/**
	 * تایید پورسانت → تراکنش مثبت کیف پول.
	 */
	public function on_commission_approved( int $commission_id, int $seller_id, float $amount ): void {
		if ( $amount <= 0 ) {
			return;
		}
		( new AccountingService() )->record(
			$seller_id,
			'commission',
			$amount,
			'commission',
			$commission_id,
			sprintf( __( 'پورسانت تاییدشده #%d', 'bespari-core' ), $commission_id )
		);
	}

	/**
	 * پرداخت برداشت → تراکنش منفی کیف پول.
	 */
	public function on_payout_paid( int $payout_id, int $seller_id, float $amount ): void {
		if ( $amount <= 0 ) {
			return;
		}
		( new AccountingService() )->record(
			$seller_id,
			'payout',
			-1 * abs( $amount ),
			'payout',
			$payout_id,
			sprintf( __( 'برداشت/پرداخت #%d', 'bespari-core' ), $payout_id )
		);
	}

	/**
	 * ثبت دستی تراکنش (شارژ/تعدیل) توسط ادمین.
	 */
	public function handle_record_transaction(): void {
		if ( ! current_user_can( 'bespari_manage_finance' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_record_transaction', 'bespari_nonce' );

		$raw        = isset( $_POST['transaction'] ) && is_array( $_POST['transaction'] ) ? wp_unslash( $_POST['transaction'] ) : array(); // phpcs:ignore
		$seller_id  = isset( $raw['seller_id'] ) ? (int) $raw['seller_id'] : 0;
		$type       = isset( $raw['type'] ) ? sanitize_key( $raw['type'] ) : 'charge';
		$amount     = Helpers::to_float( $raw['amount'] ?? 0 );
		$description = isset( $raw['description'] ) ? sanitize_text_field( $raw['description'] ) : '';

		// خروجی (برداشت/تعدیل منفی) → علامت منفی.
		if ( in_array( $type, array( 'payout' ), true ) ) {
			$amount = -1 * abs( $amount );
		}

		$redirect = add_query_arg( array( 'page' => 'bespari-accounting' ), admin_url( 'admin.php' ) );

		$result = ( new AccountingService() )->record( $seller_id, $type, $amount, 'manual', 0, $description );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), $redirect ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'saved' ), $redirect ) );
		exit;
	}

	/**
	 * صدور صورتحساب از سفارش.
	 */
	public function handle_generate_invoice(): void {
		if ( ! current_user_can( 'bespari_manage_finance' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_generate_invoice', 'bespari_nonce' );

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;

		$redirect = add_query_arg( array( 'page' => 'bespari-invoices' ), admin_url( 'admin.php' ) );

		$result = ( new AccountingService() )->generate_invoice( $order_id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), $redirect ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-invoices', 'view' => $result, 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
