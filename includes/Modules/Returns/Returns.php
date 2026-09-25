<?php
/**
 * ماژول مرجوعی‌ها — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Returns
 */

namespace Bespari\Modules\Returns;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Returns {

	public function register(): void {
		add_action( 'admin_post_bespari_save_return', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_approve_return', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_bespari_reject_return', array( $this, 'handle_reject' ) );
		add_action( 'admin_post_bespari_restock_return', array( $this, 'handle_restock' ) );
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_returns' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_save_return', 'bespari_nonce' );

		$input = isset( $_POST['return'] ) && is_array( $_POST['return'] ) ? wp_unslash( $_POST['return'] ) : array(); // phpcs:ignore

		$service = new ReturnService();
		$result  = $service->save( $input );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-returns', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_approve(): void {
		if ( ! current_user_can( 'bespari_manage_returns' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_approve_return', 'bespari_nonce' );

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore

		$service = new ReturnService();
		$result  = $service->approve( $id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-returns', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_reject(): void {
		if ( ! current_user_can( 'bespari_manage_returns' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_reject_return', 'bespari_nonce' );

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore

		$service = new ReturnService();
		$result  = $service->reject( $id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-returns', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_restock(): void {
		if ( ! current_user_can( 'bespari_manage_returns' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_restock_return', 'bespari_nonce' );

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore

		$service = new ReturnService();
		$result  = $service->restock( $id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-returns', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
