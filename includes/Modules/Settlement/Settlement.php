<?php
/**
 * ماژول تسویه — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Settlement
 */

namespace Bespari\Modules\Settlement;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settlement {

	public function register(): void {
		add_action( 'admin_post_bespari_save_settlement', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_mark_settlement_paid', array( $this, 'handle_mark_paid' ) );
		add_action( 'admin_post_bespari_delete_settlement', array( $this, 'handle_delete' ) );
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_save_settlement', 'bespari_nonce' );

		$channel_id   = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;
		$period_start = isset( $_POST['period_start'] ) ? sanitize_text_field( wp_unslash( $_POST['period_start'] ) ) : '';
		$period_end   = isset( $_POST['period_end'] ) ? sanitize_text_field( wp_unslash( $_POST['period_end'] ) ) : '';
		$title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$period_id    = isset( $_POST['period_id'] ) && '' !== $_POST['period_id'] ? (int) $_POST['period_id'] : null;

		$service = new SettlementService();
		$result  = $service->generate( $channel_id, $period_start, $period_end, $title, $period_id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-settlements', 'bespari_msg' => 'saved' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function handle_mark_paid(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_mark_settlement_paid', 'bespari_nonce' );
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new SettlementService();
		$result  = $service->mark_paid( $id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}
		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-settlements', 'bespari_msg' => 'saved' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_delete_settlement', 'bespari_nonce' );
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new SettlementService();
		$result  = $service->delete( $id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}
		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-settlements', 'bespari_msg' => 'deleted' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
