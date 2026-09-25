<?php
/**
 * ماژول درخواست‌ها — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Request
 */

namespace Bespari\Modules\Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Request {

	public function register(): void {
		add_action( 'admin_post_bespari_save_request', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_approve_request', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_bespari_reject_request', array( $this, 'handle_reject' ) );
	}

	/**
	 * ثبت درخواست: ادمین با bespari_manage_requests، بازاریاب با bespari_seller_view (فقط برای خودش).
	 */
	public function handle_save(): void {
		$can_manage = current_user_can( 'bespari_manage_requests' );
		$can_view   = current_user_can( 'bespari_seller_view' );
		if ( ! $can_manage && ! $can_view ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_save_request', 'bespari_nonce' );

		$input = isset( $_POST['request'] ) && is_array( $_POST['request'] ) ? wp_unslash( $_POST['request'] ) : array(); // phpcs:ignore

		$service = new RequestService();
		$result  = $service->save( $input );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		// بازاریاب به داشبورد خودش، ادمین به صفحه درخواست‌ها برمی‌گردد.
		$page = $can_manage ? 'bespari-requests' : 'bespari-seller-dashboard';
		wp_safe_redirect( add_query_arg( array( 'page' => $page, 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_approve(): void {
		if ( ! current_user_can( 'bespari_manage_requests' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_resolve_request', 'bespari_nonce' );

		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		$notes = isset( $_GET['notes'] ) ? sanitize_textarea_field( wp_unslash( $_GET['notes'] ) ) : ''; // phpcs:ignore

		$service = new RequestService();
		$result  = $service->approve( $id, $notes );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-requests', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_reject(): void {
		if ( ! current_user_can( 'bespari_manage_requests' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_resolve_request', 'bespari_nonce' );

		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		$notes = isset( $_GET['notes'] ) ? sanitize_textarea_field( wp_unslash( $_GET['notes'] ) ) : ''; // phpcs:ignore

		$service = new RequestService();
		$result  = $service->reject( $id, $notes );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-requests', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
