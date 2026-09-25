<?php
/**
 * ماژول پرداخت‌ها — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Payout
 */

namespace Bespari\Modules\Payout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payout {

	public function register(): void {
		add_action( 'admin_post_bespari_save_payout', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_approve_payout', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_bespari_reject_payout', array( $this, 'handle_reject' ) );
		add_action( 'admin_post_bespari_mark_payout_paid', array( $this, 'handle_mark_paid' ) );
	}

	public function handle_save(): void {
		// بازاریاب هم می‌تواند درخواست بدهد (bespari_seller_view)، ادمین هم.
		if ( ! current_user_can( 'bespari_manage_commission' ) && ! current_user_can( 'bespari_seller_view' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_save_payout', 'bespari_nonce' );
		$input = isset( $_POST['payout'] ) && is_array( $_POST['payout'] ) ? wp_unslash( $_POST['payout'] ) : array(); // phpcs:ignore
		$service = new PayoutService();
		$result  = $service->request( $input );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		$redirect_page = current_user_can( 'bespari_manage_commission' ) ? 'bespari-payouts' : 'bespari-seller-dashboard';
		wp_safe_redirect( add_query_arg( array( 'page' => $redirect_page, 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_approve(): void {
		if ( ! current_user_can( 'bespari_manage_commission' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_approve_payout', 'bespari_nonce' );
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new PayoutService();
		$result  = $service->approve( $id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-payouts', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_reject(): void {
		if ( ! current_user_can( 'bespari_manage_commission' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_reject_payout', 'bespari_nonce' );
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new PayoutService();
		$result  = $service->reject( $id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-payouts', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_mark_paid(): void {
		if ( ! current_user_can( 'bespari_manage_commission' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_mark_payout_paid', 'bespari_nonce' );
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new PayoutService();
		$result  = $service->mark_paid( $id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-payouts', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
