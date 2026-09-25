<?php
/**
 * REST احراز هویت اپ مستقل — login / logout / me.
 *
 * @package Bespari\Api
 */

namespace Bespari\Api;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuthRestController {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route( 'bespari/v1/auth', '/login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_login' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'bespari/v1/auth', '/logout', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_logout' ),
			'permission_callback' => array( $this, 'is_authed' ),
		) );

		register_rest_route( 'bespari/v1/auth', '/me', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_me' ),
			'permission_callback' => array( $this, 'is_authed' ),
		) );
	}

	/**
	 * کاربر احراز هویت شده (توکن یا کوکی).
	 */
	public function is_authed( \WP_REST_Request $request ): bool {
		return AppAuth::authenticate( $request );
	}

	/**
	 * POST /auth/login — ورود با نام کاربری/ایمیل و گذرواژه.
	 */
	public function handle_login( \WP_REST_Request $request ): \WP_REST_Response {
		$username = sanitize_text_field( (string) $request->get_param( 'username' ) );
		$password = (string) $request->get_param( 'password' );

		if ( '' === $username || '' === $password ) {
			return new \WP_REST_Response( array(
				'ok'      => false,
				'message' => __( 'نام کاربری و گذرواژه الزامی است.', 'bespari-core' ),
			), 400 );
		}

		// محدودسازی تلاش‌های ناموفق (هر IP، ۵ تلاش در ۱۰ دقیقه).
		if ( $this->is_rate_limited() ) {
			return new \WP_REST_Response( array(
				'ok'      => false,
				'message' => __( 'تعداد تلاش‌ها بیش از حد مجاز است. بعداً دوباره تلاش کنید.', 'bespari-core' ),
			), 429 );
		}

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) || ! ( $user instanceof \WP_User ) ) {
			return new \WP_REST_Response( array(
				'ok'      => false,
				'message' => __( 'نام کاربری یا گذرواژه اشتباه است.', 'bespari-core' ),
			), 401 );
		}

		if ( ! AppAuth::can_access_panel( $user ) ) {
			return new \WP_REST_Response( array(
				'ok'      => false,
				'message' => __( 'شما دسترسی به پنل بسپاری را ندارید.', 'bespari-core' ),
			), 403 );
		}

		// پاک کردن شمارنده تلاش پس از موفقیت.
		$this->clear_rate_limit();

		$token = AppAuth::issue( $user );

		return new \WP_REST_Response( array(
			'ok'    => true,
			'token' => $token,
			'user'  => $this->user_payload( $user ),
		) );
	}

	/**
	 * POST /auth/logout — ابطال توکن جاری.
	 */
	public function handle_logout( \WP_REST_Request $request ): \WP_REST_Response {
		$token = AppAuth::token_from_request( $request );

		if ( '' !== $token ) {
			AppAuth::revoke( $token );
		}

		return new \WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * GET /auth/me — اطلاعات کاربر و منوهای مجاز.
	 */
	public function handle_me( \WP_REST_Request $request ): \WP_REST_Response {
		$user = wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			return new \WP_REST_Response( array( 'ok' => false ), 401 );
		}

		if ( ! AppAuth::can_access_panel( $user ) ) {
			return new \WP_REST_Response( array(
				'ok'      => false,
				'message' => __( 'دسترسی به پنل ندارید.', 'bespari-core' ),
			), 403 );
		}

		return new \WP_REST_Response( array(
			'ok'   => true,
			'user' => $this->user_payload( $user ),
		) );
	}

	/**
	 * ساخت payload کاربر + منوها.
	 *
	 * @param \WP_User $user کاربر.
	 * @return array
	 */
	private function user_payload( \WP_User $user ): array {
		return array(
			'id'       => (int) $user->ID,
			'name'     => (string) $user->display_name,
			'email'    => (string) $user->user_email,
			'role'     => AppAuth::role_label( $user ),
			'menus'    => AppAuth::user_menus( $user ),
			'sellerId' => (int) $user->ID,
		);
	}

	/**
	 * آیا این IP بیش از حد مجاز تلاش کرده؟
	 */
	private function is_rate_limited(): bool {
		$key   = 'bespari_rl_' . md5( $this->client_ip() );
		$count = (int) get_transient( $key );

		if ( $count >= 5 ) {
			return true;
		}

		set_transient( $key, $count + 1, 600 );
		return false;
	}

	/**
	 * پاک کردن شمارنده تلاش.
	 */
	private function clear_rate_limit(): void {
		delete_transient( 'bespari_rl_' . md5( $this->client_ip() ) );
	}

	/**
	 * IP کلاینت.
	 */
	private function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0'; // phpcs:ignore
	}
}
