<?php
/**
 * ماژول پورسانت — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Commission
 */

namespace Bespari\Modules\Commission;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Commission {

	public function register(): void {
		add_action( 'admin_post_bespari_approve_commission', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_bespari_cancel_commission', array( $this, 'handle_cancel' ) );

		// هوک خودکار: پس از ایجاد/به‌روزرسانی سفارش، پورسانت ثبت شود.
		add_action( 'bespari_order_created', array( $this, 'on_order_created' ), 10, 1 );
		add_action( 'bespari_order_updated', array( $this, 'on_order_updated' ), 10, 1 );
		// هنگام کنسل سفارش، پورسانت هم کنسل شود.
		add_action( 'bespari_order_status_cancelled', array( $this, 'on_order_cancelled' ), 10, 1 );
	}

	public function handle_approve(): void {
		if ( ! current_user_can( 'bespari_manage_commission' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_approve_commission', 'bespari_nonce' );
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new CommissionService();
		$result  = $service->approve( $id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-commissions', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_cancel(): void {
		if ( ! current_user_can( 'bespari_manage_commission' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_cancel_commission', 'bespari_nonce' );
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new CommissionService();
		$result  = $service->cancel( $id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-commissions', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function on_order_created( int $order_id ): void {
		( new CommissionService() )->record_for_order( $order_id );
	}

	public function on_order_updated( int $order_id ): void {
		( new CommissionService() )->record_for_order( $order_id );
	}

	public function on_order_cancelled( int $order_id ): void {
		( new CommissionService() )->cancel_by_order( $order_id );
	}
}
