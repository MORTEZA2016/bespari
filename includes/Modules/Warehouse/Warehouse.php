<?php
/**
 * ماژول انبار — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Warehouse
 */

namespace Bespari\Modules\Warehouse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Warehouse {

	public function register(): void {
		add_action( 'admin_post_bespari_adjust_stock', array( $this, 'handle_adjust' ) );

		// هوک خودکار: کسر موجودی هنگام ایجاد سفارش، بازگردانی هنگام لغو.
		add_action( 'bespari_order_created', array( $this, 'on_order_created' ), 10, 1 );
		add_action( 'bespari_order_status_cancelled', array( $this, 'on_order_cancelled' ), 20, 1 );
	}

	public function handle_adjust(): void {
		if ( ! current_user_can( 'bespari_manage_warehouse' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_adjust_stock', 'bespari_nonce' );

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0; // phpcs:ignore
		$type       = isset( $_POST['movement_type'] ) ? sanitize_key( wp_unslash( $_POST['movement_type'] ) ) : ''; // phpcs:ignore
		$quantity    = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 0; // phpcs:ignore
		$notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : ''; // phpcs:ignore

		$service = new StockService();
		$result  = $service->adjust( $product_id, $type, $quantity, $notes );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), wp_get_referer() ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-warehouse', 'bespari_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function on_order_created( int $order_id ): void {
		( new StockService() )->deduct_for_order( $order_id );
	}

	public function on_order_cancelled( int $order_id ): void {
		( new StockService() )->restore_for_order( $order_id );
	}
}
