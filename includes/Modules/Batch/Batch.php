<?php
/**
 * ماژول محموله‌های کانالی — ثبت هوک‌ها (بازطراحی تسویه).
 *
 * @package Bespari\Modules\Batch
 */

namespace Bespari\Modules\Batch;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Batch {

	public function register(): void {
		add_action( 'admin_post_bespari_create_batch', array( $this, 'handle_create' ) );
		add_action( 'admin_post_bespari_ship_batch', array( $this, 'handle_ship' ) );
		add_action( 'admin_post_bespari_settle_batch_cash', array( $this, 'handle_settle_cash' ) );
		add_action( 'admin_post_bespari_settle_batch_credit', array( $this, 'handle_settle_credit' ) );
		add_action( 'admin_post_bespari_delete_batch', array( $this, 'handle_delete' ) );

		// بازمحاسبه محموله/صورت‌حساب پس از تایید مرجوعی.
		add_action( 'bespari_return_approved', array( $this, 'on_return_approved' ), 10, 2 );
	}

	/**
	 * بازمحاسبه مبالغ محموله/صورت‌حساب هنگام مرجوعی.
	 */
	public function on_return_approved( int $order_id ): void {
		( new BatchService() )->recalc_for_return( $order_id );
		( new \Bespari\Modules\ChannelInvoice\ChannelInvoiceService() )->recalc_for_return( $order_id );
	}

	/**
	 * خروجی خطا/موفقیت استاندارد به صفحه سفارشات.
	 */
	private function redirect( \WP_Error|bool $result, array $args = array() ): void {
		$query = array( 'page' => 'bespari-orders' );

		if ( is_wp_error( $result ) ) {
			$query['bespari_msg']   = 'error';
			$query['bespari_error'] = urlencode( $result->get_error_message() );
		} else {
			$query['bespari_msg'] = 'saved';
		}

		wp_safe_redirect( add_query_arg( array_merge( $query, $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_create(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_create_batch', 'bespari_nonce' );

		$channel_id = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;
		$order_ids  = isset( $_POST['order_ids'] ) && is_array( $_POST['order_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['order_ids'] ) ) : array(); // phpcs:ignore

		$service = new BatchService();
		$result  = $service->create_from_orders( $channel_id, $order_ids );

		$args = array();
		if ( $channel_id > 0 ) {
			$args['channel_id'] = $channel_id;
			$args['subtab']     = 'batches';
		}

		$this->redirect( $result, $args );
	}

	public function handle_ship(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_ship_batch', 'bespari_nonce' );

		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new BatchService();
		$this->redirect( $service->ship( $id ), array( 'subtab' => 'batches' ) );
	}

	public function handle_settle_cash(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_settle_batch_cash', 'bespari_nonce' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$code = isset( $_POST['tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_code'] ) ) : '';
		if ( '' === $code ) {
			$this->redirect( new \WP_Error( 'bespari_batch_no_code', __( 'کد پیگیری تسویه را وارد کنید.', 'bespari-core' ) ), array( 'subtab' => 'batches' ) );
		}

		$service = new BatchService();
		$this->redirect( $service->settle_cash( $id, $code ), array( 'subtab' => 'batches' ) );
	}

	public function handle_settle_credit(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_settle_batch_credit', 'bespari_nonce' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$code = isset( $_POST['tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_code'] ) ) : '';
		if ( '' === $code ) {
			$this->redirect( new \WP_Error( 'bespari_batch_no_code', __( 'کد پیگیری تسویه را وارد کنید.', 'bespari-core' ) ), array( 'subtab' => 'batches' ) );
		}

		$service = new BatchService();
		$this->redirect( $service->settle_credit( $id, $code ), array( 'subtab' => 'batches' ) );
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_settings' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_delete_batch', 'bespari_nonce' );

		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new BatchService();
		$this->redirect( $service->delete( $id ), array( 'subtab' => 'batches' ) );
	}
}
