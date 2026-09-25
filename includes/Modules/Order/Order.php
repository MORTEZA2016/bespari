<?php
/**
 * ماژول سفارشات — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Order
 */

namespace Bespari\Modules\Order;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Order {

	public function register(): void {
		add_action( 'admin_post_bespari_save_order', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_delete_order', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_bespari_update_order_status', array( $this, 'handle_update_status' ) );
		add_action( 'admin_post_bespari_order_tracking', array( $this, 'handle_tracking' ) );
		add_action( 'admin_post_bespari_order_settlement_ref', array( $this, 'handle_settlement_ref' ) );
		add_action( 'wp_ajax_bespari_order_preview', array( $this, 'handle_preview' ) );
	}

	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_orders' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_save_order', 'bespari_nonce' );

		$service = new OrderService();
		$id      = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$input   = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array(); // phpcs:ignore

		// آیتم‌ها از فیلد جداگانه.
		if ( isset( $_POST['items'] ) && is_array( $_POST['items'] ) ) {
			$input['items'] = wp_unslash( $_POST['items'] ); // phpcs:ignore
		}

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
			array( 'page' => 'bespari-orders', 'bespari_msg' => 'saved' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_orders' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_delete_order', 'bespari_nonce' );
		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new OrderService();
		$result  = $service->delete( $id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}
		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-orders', 'bespari_msg' => 'deleted' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	public function handle_update_status(): void {
		if ( ! current_user_can( 'bespari_manage_orders' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_update_order_status', 'bespari_nonce' );
		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$service = new OrderService();
		$result  = $service->update_status( $id, $status );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}
		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-orders', 'bespari_msg' => 'saved' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * ثبت کد رهگیری سفارش (حالت فاکتور به فاکتور) → در حال انجام به ارسال شده.
	 */
	public function handle_tracking(): void {
		if ( ! current_user_can( 'bespari_manage_orders' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_order_tracking', 'bespari_nonce' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$code = isset( $_POST['tracking_code'] ) ? sanitize_text_field( wp_unslash( $_POST['tracking_code'] ) ) : '';
		$ch   = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;

		$query = array( 'page' => 'bespari-orders' );
		if ( $ch > 0 ) {
			$query['channel_id'] = $ch;
		}

		if ( $id <= 0 || '' === $code ) {
			$query['bespari_msg']   = 'error';
			$query['bespari_error'] = urlencode( __( 'کد رهگیری الزامی است.', 'bespari-core' ) );
			wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
			exit;
		}

		$service = new OrderService();
		$result  = $service->update_status( $id, 'shipped' );

		// ثبت برگه ارسال با کد رهگیری.
		if ( ! is_wp_error( $result ) ) {
			$shipment_service = new \Bespari\Modules\Shipping\ShipmentService();
			$existing         = ( new \Bespari\Modules\Shipping\ShipmentRepository() )->find_by_order( $id );
			if ( $existing ) {
				$shipment_service->update_status( $existing->id, 'shipped' );
				$shipment_service->save( $existing->id, array( 'tracking_code' => $code ) );
			} else {
				$shipment_service->save( 0, array( 'order_id' => $id, 'tracking_code' => $code ) );
			}
		}

		if ( is_wp_error( $result ) ) {
			$query['bespari_msg']   = 'error';
			$query['bespari_error'] = urlencode( $result->get_error_message() );
		} else {
			$query['bespari_msg'] = 'saved';
		}

		wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * ثبت کد ارجاع تسویه سفارش (حالت فاکتور به فاکتور) → تسویه شده.
	 */
	public function handle_settlement_ref(): void {
		if ( ! current_user_can( 'bespari_manage_settlement' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_order_settlement_ref', 'bespari_nonce' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$code = isset( $_POST['settlement_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['settlement_ref'] ) ) : '';
		$ch   = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;

		$query = array( 'page' => 'bespari-orders' );
		if ( $ch > 0 ) {
			$query['channel_id'] = $ch;
		}

		if ( $id <= 0 || '' === $code ) {
			$query['bespari_msg']   = 'error';
			$query['bespari_error'] = urlencode( __( 'کد ارجاع تسویه الزامی است.', 'bespari-core' ) );
			wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
			exit;
		}

		$service = new OrderService();
		$result  = $service->mark_settled( $id, $code );

		if ( is_wp_error( $result ) ) {
			$query['bespari_msg']   = 'error';
			$query['bespari_error'] = urlencode( $result->get_error_message() );
		} else {
			$query['bespari_msg'] = 'saved';
		}

		wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * پیش‌نمایش محاسبه سفارش (AJAX).
	 */
	public function handle_preview(): void {
		check_ajax_referer( 'bespari_nonce', 'nonce' );
		if ( ! current_user_can( 'bespari_manage_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'bespari-core' ) ) );
		}
		$channel_id = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;
		$sale_type  = isset( $_POST['sale_type'] ) ? sanitize_key( $_POST['sale_type'] ) : 'cash';
		$seller_id  = isset( $_POST['seller_id'] ) ? (int) $_POST['seller_id'] : 0;
		$items      = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore

		if ( ! $channel_id || empty( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'کانال و حداقل یک محصول الزامی است.', 'bespari-core' ) ) );
		}

		$calc   = new \Bespari\Modules\Finance\Calculator();
		$result = $calc->calculate_order( $channel_id, $sale_type, $seller_id, $items );
		wp_send_json_success( $result );
	}
}
