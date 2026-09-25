<?php
/**
 * ماژول صورت‌حساب‌های کانالی — ثبت هوک‌ها (بازطراحی تسویه).
 *
 * @package Bespari\Modules\ChannelInvoice
 */

namespace Bespari\Modules\ChannelInvoice;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChannelInvoice {

	public function register(): void {
		add_action( 'admin_post_bespari_create_invoice', array( $this, 'handle_create' ) );
		add_action( 'admin_post_bespari_settle_invoice_cash', array( $this, 'handle_settle_cash' ) );
		add_action( 'admin_post_bespari_settle_invoice_credit', array( $this, 'handle_settle_credit' ) );
	}

	/**
	 * خروجی خطا/موفقیت استاندارد به صفحه سفارشات.
	 */
	private function redirect( \WP_Error|bool $result, int $channel_id, string $subtab ): void {
		$query = array( 'page' => 'bespari-orders', 'subtab' => $subtab );

		if ( is_wp_error( $result ) ) {
			$query['bespari_msg']   = 'error';
			$query['bespari_error'] = urlencode( $result->get_error_message() );
		} else {
			$query['bespari_msg'] = 'saved';
		}

		if ( $channel_id > 0 ) {
			$query['channel_id'] = $channel_id;
		}

		wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_create(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_create_invoice', 'bespari_nonce' );

		$channel_id = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;
		$batch_ids  = isset( $_POST['batch_ids'] ) && is_array( $_POST['batch_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['batch_ids'] ) ) : array(); // phpcs:ignore
		$days       = isset( $_POST['period_days'] ) ? (int) $_POST['period_days'] : 0;
		$cash_due   = isset( $_POST['cash_due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['cash_due_date'] ) ) : '';
		$credit_due = isset( $_POST['credit_due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['credit_due_date'] ) ) : '';

		$service = new ChannelInvoiceService();
		$result  = $service->create_from_batches( $channel_id, $batch_ids, $days, $cash_due, $credit_due );

		$this->redirect( $result, $channel_id, 'invoices' );
	}

	public function handle_settle_cash(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_settle_invoice_cash', 'bespari_nonce' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$code = isset( $_POST['tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_code'] ) ) : '';
		$cid  = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;

		if ( '' === $code ) {
			$this->redirect( new \WP_Error( 'bespari_cinv_no_code', __( 'کد پیگیری تسویه را وارد کنید.', 'bespari-core' ) ), $cid, 'invoices' );
		}

		$service = new ChannelInvoiceService();
		$this->redirect( $service->settle_cash( $id, $code ), $cid, 'invoices' );
	}

	public function handle_settle_credit(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_settle_invoice_credit', 'bespari_nonce' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$code = isset( $_POST['tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_code'] ) ) : '';
		$cid  = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;

		if ( '' === $code ) {
			$this->redirect( new \WP_Error( 'bespari_cinv_no_code', __( 'کد پیگیری تسویه را وارد کنید.', 'bespari-core' ) ), $cid, 'invoices' );
		}

		$service = new ChannelInvoiceService();
		$this->redirect( $service->settle_credit( $id, $code ), $cid, 'invoices' );
	}
}
