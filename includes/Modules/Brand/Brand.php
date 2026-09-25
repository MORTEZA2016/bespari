<?php
/**
 * ماژول برندها — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Brand
 */

namespace Bespari\Modules\Brand;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Brand {

	public function register(): void {
		add_action( 'admin_post_bespari_save_brand', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_delete_brand', array( $this, 'handle_delete' ) );
	}

	/**
	 * ذخیره برند.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_brands' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_save_brand', 'bespari_nonce' );

		$service = new BrandService();
		$id      = isset( $_POST['brand_id'] ) ? (int) $_POST['brand_id'] : 0;
		$input   = isset( $_POST['brand'] ) && is_array( $_POST['brand'] ) ? wp_unslash( $_POST['brand'] ) : array(); // phpcs:ignore

		$result = $id ? $service->update( $id, $input ) : $service->create( $input );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-brands', 'bespari_msg' => 'saved' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * حذف برند.
	 */
	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_brands' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_delete_brand', 'bespari_nonce' );

		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new BrandService();
		$service->delete( $id );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-brands', 'bespari_msg' => 'deleted' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
