<?php
/**
 * ماژول ارسال — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Shipping
 */

namespace Bespari\Modules\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shipping {

	public function register(): void {
		add_action( 'admin_post_bespari_save_shipment', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_update_shipment_status', array( $this, 'handle_update_status' ) );
		add_action( 'admin_post_bespari_delete_shipment', array( $this, 'handle_delete' ) );
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_shipping' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_save_shipment', 'bespari_nonce' );

		$id      = isset( $_POST['shipment_id'] ) ? (int) $_POST['shipment_id'] : 0; // phpcs:ignore
		$input   = isset( $_POST['shipment'] ) && is_array( $_POST['shipment'] ) ? wp_unslash( $_POST['shipment'] ) : array(); // phpcs:ignore

		$service = new ShipmentService();
		$result  = $service->save( $id, $input );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-shipments', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_update_status(): void {
		if ( ! current_user_can( 'bespari_manage_shipping' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_update_shipment_status', 'bespari_nonce' );

		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore

		$service = new ShipmentService();
		$result  = $service->update_status( $id, $status );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-shipments', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_shipping' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_delete_shipment', 'bespari_nonce' );

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore

		$service = new ShipmentService();
		$result  = $service->delete( $id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-shipments', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
