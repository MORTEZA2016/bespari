<?php
/**
 * REST خط تولید — لیست روزانه، آماده‌سازی، انتقال و ارسال.
 *
 * @package Bespari\Api
 */

namespace Bespari\Api;

use Bespari\Modules\Production\ProductionService;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductionRestController {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$ns = 'bespari/v1/production';

		register_rest_route( $ns, '/list', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_list' ),
			'permission_callback' => array( $this, 'can_view_production' ),
		) );

		register_rest_route( $ns, '/ready', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_ready' ),
			'permission_callback' => array( $this, 'can_view_production' ),
		) );

		register_rest_route( $ns, '/move', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_move' ),
			'permission_callback' => array( $this, 'can_view_production' ),
		) );

		register_rest_route( $ns, '/ship', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_ship' ),
			'permission_callback' => array( $this, 'can_ship' ),
		) );
	}

	/**
	 * گارد دسترسی مدیر تولید.
	 */
	public function can_view_production( $request ): bool {
		if ( ! AppAuth::authenticate( $request ) ) {
			return false;
		}
		return current_user_can( 'bespari_production_view' );
	}

	/**
	 * گارد ارسال: بازاریاب یا مدیر سفارشات.
	 */
	public function can_ship( $request ): bool {
		if ( ! AppAuth::authenticate( $request ) ) {
			return false;
		}
		return current_user_can( 'bespari_seller_view' ) || current_user_can( 'bespari_manage_orders' );
	}

	public function handle_list( \WP_REST_Request $request ): \WP_REST_Response {
		$date = (string) ( $request->get_param( 'date' ) ?? '' );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = current_time( 'Y-m-d' );
		}

		$data = ( new ProductionService() )->list_by_date( $date );

		return new \WP_REST_Response( array_merge( array( 'ok' => true ), $data ) );
	}

	public function handle_ready( \WP_REST_Request $request ): \WP_REST_Response {
		$order_id = (int) ( $request->get_param( 'order_id' ) ?? 0 );

		$result = ( new ProductionService() )->mark_ready( $order_id );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new \WP_REST_Response( array( 'ok' => true ) );
	}

	public function handle_move( \WP_REST_Request $request ): \WP_REST_Response {
		$order_id = (int) ( $request->get_param( 'order_id' ) ?? 0 );
		$date     = (string) ( $request->get_param( 'date' ) ?? '' );

		$result = ( new ProductionService() )->move_order( $order_id, $date );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new \WP_REST_Response( array( 'ok' => true ) );
	}

	public function handle_ship( \WP_REST_Request $request ): \WP_REST_Response {
		$order_id = (int) ( $request->get_param( 'order_id' ) ?? 0 );

		$result = ( new ProductionService() )->mark_shipped( $order_id );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
		}

		return new \WP_REST_Response( array( 'ok' => true ) );
	}
}
