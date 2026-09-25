<?php
/**
 * احراز هویت مستقل اپ بسپاری — توکن (نه کوکی وردپرس).
 *
 * توکن:  <user_id>:<random 64 hex>
 * فقط هش sha256 بخش تصادفی در usermeta ذخیره می‌شود.
 *
 * @package Bespari\Api
 */

namespace Bespari\Api;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AppAuth {

	public const META_KEY   = 'bespari_app_tokens';
	private const LIFETIME   = 604800; // ۷ روز.

	/**
	 * منوهای پنل: key => [label, caps (با | چند مقدار), icon].
	 *
	 * @return array
	 */
	public static function menus(): array {
		return array(
			'dashboard'   => array( __( 'داشبورد', 'bespari-core' ), 'bespari_read_dashboard|bespari_seller_view', 'dashicons-dashboard' ),
			'pos'         => array( __( 'ثبت سفارش (POS)', 'bespari-core' ), 'bespari_manage_orders|bespari_seller_view', 'dashicons-cart' ),
			'production'  => array( __( 'خط تولید', 'bespari-core' ), 'bespari_production_view', 'dashicons-clipboard' ),
			'orders'      => array( __( 'سفارشات', 'bespari-core' ), 'bespari_manage_orders|bespari_seller_view', 'dashicons-list-view' ),
			'products'    => array( __( 'محصولات', 'bespari-core' ), 'bespari_manage_products|bespari_seller_view', 'dashicons-products' ),
			'channels'    => array( __( 'کانال‌های فروش', 'bespari-core' ), 'bespari_manage_settings', 'dashicons-networking' ),
			'sellers'     => array( __( 'بازاریاب‌ها', 'bespari-core' ), 'bespari_manage_sellers', 'dashicons-groups' ),
			'commissions' => array( __( 'پورسانت‌ها', 'bespari-core' ), 'bespari_manage_commission|bespari_seller_view', 'dashicons-money-alt' ),
			'warehouse'   => array( __( 'انبار', 'bespari-core' ), 'bespari_manage_warehouse', 'dashicons-portfolio' ),
			'reports'     => array( __( 'گزارشات', 'bespari-core' ), 'bespari_view_reports', 'dashicons-chart-bar' ),
			'brands'          => array( __( 'برندها', 'bespari-core' ), 'bespari_manage_brands', 'dashicons-tag' ),
			'pricing'         => array( __( 'قیمت‌گذاری', 'bespari-core' ), 'bespari_manage_pricing', 'dashicons-money' ),
			'settlements'     => array( __( 'تسویه', 'bespari-core' ), 'bespari_manage_settlement', 'dashicons-clipboard' ),
			'payouts'         => array( __( 'پرداخت‌ها', 'bespari-core' ), 'bespari_manage_commission|bespari_seller_view', 'dashicons-bank' ),
			'shipments'       => array( __( 'ارسال‌ها', 'bespari-core' ), 'bespari_manage_shipping', 'dashicons-truck' ),
			'returns'         => array( __( 'مرجوعی‌ها', 'bespari-core' ), 'bespari_manage_returns', 'dashicons-undo' ),
			'requests'        => array( __( 'درخواست‌ها', 'bespari-core' ), 'bespari_manage_requests|bespari_seller_view', 'dashicons-email-alt' ),
			'accounting'      => array( __( 'حسابداری', 'bespari-core' ), 'bespari_manage_finance', 'dashicons-calculator' ),
			'invoices'        => array( __( 'صورتحساب‌ها', 'bespari-core' ), 'bespari_manage_finance', 'dashicons-media-document' ),
			'agents'          => array( __( 'اتصال‌ها', 'bespari-core' ), 'bespari_manage_settings', 'dashicons-admin-network' ),
			'settings'        => array( __( 'تنظیمات', 'bespari-core' ), 'bespari_manage_settings', 'dashicons-admin-generic' ),
			'seller_dashboard'=> array( __( 'داشبورد بازاریاب', 'bespari-core' ), 'bespari_seller_view', 'dashicons-businessperson' ),
			'users'           => array( __( 'کاربران', 'bespari-core' ), 'bespari_manage_users', 'dashicons-admin-users' ),
		);
	}

	/**
	 * استخراج توکن از هدر Authorization (Bearer).
	 *
	 * @param \WP_REST_Request|null $request درخواست REST.
	 * @return string توکن خام یا رشته خالی.
	 */
	public static function token_from_request( $request = null ): string {
		$header = '';

		if ( $request instanceof \WP_REST_Request ) {
			$header = (string) $request->get_header( 'authorization' );
		}

		if ( '' === $header && isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ); // phpcs:ignore
		}

		if ( 0 !== stripos( $header, 'Bearer ' ) ) {
			return '';
		}

		return trim( substr( $header, 7 ) );
	}

	/**
	 * صدور توکن جدید برای کاربر.
	 *
	 * @param \WP_User $user کاربر.
	 * @return string توکن خام (یک بار به کاربر داده می‌شود).
	 */
	public static function issue( \WP_User $user ): string {
		$random = bin2hex( random_bytes( 32 ) );
		$token  = $user->ID . ':' . $random;

		$tokens = self::raw_tokens( $user->ID );
		$now    = time();

		// حذف توکن‌های منقضی.
		$tokens = array_filter(
			$tokens,
			static function ( $t ) use ( $now ) {
				return isset( $t['expires'] ) && $t['expires'] > $now;
			}
		);

		$tokens[] = array(
			'hash'    => hash( 'sha256', $random ),
			'expires' => $now + self::LIFETIME,
			'created' => $now,
			'ua'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '', // phpcs:ignore
		);

		update_user_meta( $user->ID, self::META_KEY, array_values( $tokens ) );

		return $token;
	}

	/**
	 * تایید توکن و بازگرداندن شناسه کاربر.
	 *
	 * @param string $token توکن خام.
	 * @return int شناسه کاربر یا ۰.
	 */
	public static function verify( string $token ): int {
		$parts = explode( ':', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return 0;
		}

		$user_id = (int) $parts[0];
		$random  = $parts[1];

		if ( $user_id < 1 || ! preg_match( '/^[0-9a-f]{64}$/', $random ) ) {
			return 0;
		}

		$tokens = self::raw_tokens( $user_id );
		$now    = time();
		$hash   = hash( 'sha256', $random );

		foreach ( $tokens as $t ) {
			if ( isset( $t['hash'] ) && hash_equals( $t['hash'], $hash ) ) {
				if ( isset( $t['expires'] ) && $t['expires'] > $now ) {
					return $user_id;
				}
				return 0; // منقضی.
			}
		}

		return 0;
	}

	/**
	 * ابطال یک توکن.
	 *
	 * @param string $token توکن خام.
	 * @return void
	 */
	public static function revoke( string $token ): void {
		$parts = explode( ':', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return;
		}

		$user_id = (int) $parts[0];
		$random  = $parts[1];

		if ( $user_id < 1 || ! preg_match( '/^[0-9a-f]{64}$/', $random ) ) {
			return;
		}

		$tokens = self::raw_tokens( $user_id );
		$hash   = hash( 'sha256', $random );

		$tokens = array_filter(
			$tokens,
			static function ( $t ) use ( $hash ) {
				return ! ( isset( $t['hash'] ) && hash_equals( $t['hash'], $hash ) );
			}
		);

		update_user_meta( $user_id, self::META_KEY, array_values( $tokens ) );
	}

	/**
	 * احراز هویت یک درخواست REST — یا توکن یا کوکی وردپرس.
	 * در صورت موفقیت current user را ست می‌کند.
	 *
	 * @param \WP_REST_Request|null $request درخواست.
	 * @return bool
	 */
	public static function authenticate( $request = null ): bool {
		$token = self::token_from_request( $request );

		if ( '' !== $token ) {
			$user_id = self::verify( $token );
			if ( $user_id > 0 ) {
				wp_set_current_user( $user_id );
				return true;
			}
			return false;
		}

		return is_user_logged_in();
	}

	/**
	 * آیا کاربر دسترسی به یک منو را دارد؟
	 *
	 * @param \WP_User $user کاربر.
	 * @param string   $caps  یک یا چند cap با |.
	 * @return bool
	 */
	public static function user_can( \WP_User $user, string $caps ): bool {
		if ( '' === $caps ) {
			return true;
		}

		foreach ( explode( '|', $caps ) as $cap ) {
			if ( $user->has_cap( trim( $cap ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * منوهای مجاز برای کاربر.
	 *
	 * @param \WP_User $user کاربر.
	 * @return array لیست [key, label, icon].
	 */
	public static function user_menus( \WP_User $user ): array {
		$allowed = array();

		foreach ( self::menus() as $key => $item ) {
			if ( self::user_can( $user, $item[1] ) ) {
				$allowed[] = array(
					'key'   => $key,
					'label' => $item[0],
					'icon'  => $item[2],
				);
			}
		}

		return $allowed;
	}

	/**
	 * آیا کاربر اصلاً به پنل دسترسی دارد؟
	 *
	 * @param \WP_User $user کاربر.
	 * @return bool
	 */
	public static function can_access_panel( \WP_User $user ): bool {
		return (bool) self::user_menus( $user );
	}

	/**
	 * برچسب نقش کاربر برای نمایش.
	 *
	 * @param \WP_User $user کاربر.
	 * @return string
	 */
	public static function role_label( \WP_User $user ): string {
		if ( $user->has_cap( 'bespari_read_dashboard' ) ) {
			return __( 'مدیر', 'bespari-core' );
		}
		if ( $user->has_cap( 'bespari_production_view' ) ) {
			return __( 'مدیر تولید', 'bespari-core' );
		}
		if ( $user->has_cap( 'bespari_seller_view' ) ) {
			return __( 'بازاریاب', 'bespari-core' );
		}
		if ( $user->has_cap( 'bespari_manage_warehouse' ) ) {
			return __( 'انباردار', 'bespari-core' );
		}
		if ( $user->has_cap( 'bespari_manage_finance' ) ) {
			return __( 'حسابدار', 'bespari-core' );
		}
		return __( 'کاربر', 'bespari-core' );
	}

	/**
	 * خواندن توکن‌های ذخیره‌شده کاربر.
	 *
	 * @param int $user_id شناسه کاربر.
	 * @return array
	 */
	private static function raw_tokens( int $user_id ): array {
		$tokens = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $tokens ) ? $tokens : array();
	}
}
