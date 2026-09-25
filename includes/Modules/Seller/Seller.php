<?php
/**
 * ماژول بازاریاب‌ها — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Seller
 */

namespace Bespari\Modules\Seller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seller {

	public function register(): void {
		add_action( 'admin_post_bespari_save_seller', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_delete_seller', array( $this, 'handle_delete' ) );
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_sellers' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_save_seller', 'bespari_nonce' );

		$service = new SellerService();
		$id      = isset( $_POST['seller_id'] ) ? (int) $_POST['seller_id'] : 0;
		$input   = isset( $_POST['seller'] ) && is_array( $_POST['seller'] ) ? wp_unslash( $_POST['seller'] ) : array(); // phpcs:ignore

		if ( $id ) {
			$result = $service->update( $id, $input );
		} else {
			$result = $service->create( $input );
		}

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-sellers', 'bespari_msg' => 'saved' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_sellers' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_delete_seller', 'bespari_nonce' );
		$id          = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$delete_user = isset( $_GET['force'] ) && '1' === $_GET['force'];
		$service     = new SellerService();
		$result      = $service->delete( $id, $delete_user );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}
		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-sellers', 'bespari_msg' => 'deleted' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
