<?php
/**
 * REST اعلان‌های اپ مستقل — list / unread-count / mark-read.
 *
 * @package Bespari\Api
 */

namespace Bespari\Api;

use Bespari\Modules\Notification\NotificationService;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NotificationRestController {

	private NotificationService $service;

	public function __construct() {
		$this->service = new NotificationService();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route( 'bespari/v1/notifications', '/list', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_list' ),
			'permission_callback' => array( $this, 'is_authed' ),
		) );

		register_rest_route( 'bespari/v1/notifications', '/unread-count', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_unread_count' ),
			'permission_callback' => array( $this, 'is_authed' ),
		) );

		register_rest_route( 'bespari/v1/notifications', '/read', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_mark_read' ),
			'permission_callback' => array( $this, 'is_authed' ),
		) );

		register_rest_route( 'bespari/v1/notifications', '/read-all', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_mark_all_read' ),
			'permission_callback' => array( $this, 'is_authed' ),
		) );
	}

	public function is_authed( \WP_REST_Request $request ): bool {
		return AppAuth::authenticate( $request );
	}

	/**
	 * GET /notifications/list
	 */
	public function handle_list( \WP_REST_Request $request ): \WP_REST_Response {
		$limit = min( 50, max( 1, (int) ( $request->get_param( 'limit' ) ?: 30 ) ) );

		return new \WP_REST_Response( array(
			'ok'            => true,
			'notifications' => $this->service->list( get_current_user_id(), $limit ),
			'unread'        => $this->service->unread_count( get_current_user_id() ),
		) );
	}

	/**
	 * GET /notifications/unread-count
	 */
	public function handle_unread_count( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( array(
			'ok'     => true,
			'unread' => $this->service->unread_count( get_current_user_id() ),
		) );
	}

	/**
	 * POST /notifications/read  { id }
	 */
	public function handle_mark_read( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		if ( $id < 1 ) {
			return new \WP_REST_Response( array(
				'ok'      => false,
				'message' => __( 'شناسه اعلان نامعتبر است.', 'bespari-core' ),
			), 400 );
		}

		$this->service->mark_read( $id, get_current_user_id() );

		return new \WP_REST_Response( array(
			'ok'     => true,
			'unread' => $this->service->unread_count( get_current_user_id() ),
		) );
	}

	/**
	 * POST /notifications/read-all
	 */
	public function handle_mark_all_read( \WP_REST_Request $request ): \WP_REST_Response {
		$this->service->mark_all_read( get_current_user_id() );

		return new \WP_REST_Response( array(
			'ok'     => true,
			'unread' => 0,
		) );
	}
}
