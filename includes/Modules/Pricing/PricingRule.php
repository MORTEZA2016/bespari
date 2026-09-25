<?php
/**
 * ماژول قوانین قیمت‌گذاری — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Pricing
 */

namespace Bespari\Modules\Pricing;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PricingRule {

	public function register(): void {
		add_action( 'admin_post_bespari_save_pricing_rule', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_delete_pricing_rule', array( $this, 'handle_delete' ) );

		// پیش‌نمایش قیمت (AJAX).
		add_action( 'wp_ajax_bespari_preview_price', array( $this, 'ajax_preview' ) );
	}

	/**
	 * ذخیره قانون.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_pricing' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_save_pricing_rule', 'bespari_nonce' );

		$service = new PricingRuleService();
		$id      = isset( $_POST['rule_id'] ) ? (int) $_POST['rule_id'] : 0;
		$input   = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array(); // phpcs:ignore

		$result = $id ? $service->update( $id, $input ) : $service->create( $input );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-pricing', 'bespari_msg' => 'saved' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * حذف قانون.
	 */
	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_pricing' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_delete_pricing_rule', 'bespari_nonce' );

		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new PricingRuleService();
		$service->delete( $id );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-pricing', 'bespari_msg' => 'deleted' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * پیش‌نمایش قیمت از طریق AJAX.
	 */
	public function ajax_preview(): void {
		check_ajax_referer( 'bespari_ajax', 'nonce' );

		if ( ! current_user_can( 'bespari_manage_pricing' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'bespari-core' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$channel_id = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;
		$sale_type  = isset( $_POST['sale_type'] ) ? sanitize_key( wp_unslash( $_POST['sale_type'] ) ) : 'cash';
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, (int) $_POST['quantity'] ) : 1;

		if ( ! $product_id || ! $channel_id ) {
			wp_send_json_error( array( 'message' => __( 'محصول و کانال الزامی هستند.', 'bespari-core' ) ) );
		}

		$service = new PricingRuleService();
		$result  = $service->preview( $product_id, $channel_id, $sale_type, $quantity );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}
}
