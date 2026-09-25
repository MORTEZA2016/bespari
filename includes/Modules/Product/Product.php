<?php
/**
 * ماژول محصولات — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Product
 */

namespace Bespari\Modules\Product;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Product {

	public function register(): void {
		add_action( 'admin_post_bespari_save_product', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_delete_product', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_bespari_sync_wc_products', array( $this, 'handle_sync' ) );

		// هوک‌های سینک خودکار با ووکامرس (در صورت فعال بودن).
		add_action( 'woocommerce_update_product', array( $this, 'on_wc_product_change' ), 10, 1 );
		add_action( 'woocommerce_new_product', array( $this, 'on_wc_product_change' ), 10, 1 );

		// سینک کامل محصولات هنگام نصب/فعال‌سازی افزونه.
		add_action( 'bespari_initial_product_sync', array( $this, 'handle_sync_silent' ) );
	}

	/**
	 * سینک کامل ووکامرس بدون redirect (برای cron فعال‌سازی).
	 */
	public function handle_sync_silent(): void {
		if ( ! class_exists( '\WC_Product_Query' ) ) {
			return;
		}
		$service = new ProductService();
		$service->sync_from_woocommerce();
	}

	/**
	 * ذخیره محصول.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_products' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_save_product', 'bespari_nonce' );

		$service = new ProductService();
		$id      = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$input   = isset( $_POST['product'] ) && is_array( $_POST['product'] ) ? wp_unslash( $_POST['product'] ) : array(); // phpcs:ignore

		$result = $id ? $service->update( $id, $input ) : $service->create( $input );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-products', 'bespari_msg' => 'saved' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * حذف محصول.
	 */
	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_products' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_delete_product', 'bespari_nonce' );

		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new ProductService();
		$service->delete( $id );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-products', 'bespari_msg' => 'deleted' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * سینک دستی محصولات از ووکامرس.
	 */
	public function handle_sync(): void {
		if ( ! current_user_can( 'bespari_manage_products' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_sync_wc', 'bespari_nonce' );

		$service = new ProductService();
		$count   = $service->sync_from_woocommerce();

		wp_safe_redirect( add_query_arg(
			array(
				'page'         => 'bespari-products',
				'bespari_msg'  => 'synced',
				'synced_count' => $count,
			),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * سینک خودکار هنگام تغییر محصول ووکامرس.
	 *
	 * @param int $product_id آی‌دی محصول ووکامرس.
	 */
	public function on_wc_product_change( int $product_id ): void {
		if ( ! class_exists( '\WC_Product_Query' ) ) {
			return;
		}

		$wc_product = wc_get_product( $product_id );

		if ( ! $wc_product ) {
			return;
		}

		$service = new ProductService();
		// استفاده از متد خصوصی upsert — برای سینک تکی مستقیماً دیتابیس را به‌روز می‌کنیم.
		$repo     = new ProductRepository();
		$existing = $repo->get_by_wc_id( $product_id );

		$base_data = array(
			'name'          => $wc_product->get_name(),
			'sku'           => (string) $wc_product->get_sku(),
			'wc_product_id' => $product_id,
			'base_price'    => (float) $wc_product->get_price(),
			'stock'         => (int) $wc_product->get_stock_quantity(),
			'updated_at'    => \Bespari\Support\Helpers::now(),
		);

		if ( $existing ) {
			$repo->update( $existing->id, $base_data );
		} else {
			$base_data['status']     = 1;
			$base_data['created_at'] = \Bespari\Support\Helpers::now();
			$repo->insert( $base_data );
		}
	}
}
