<?php
/**
 * توابع کمکی عمومی افزونه.
 *
 * @package Bespari\Support
 */

namespace Bespari\Support;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helpers {

	/**
	 * دریافت تنظیمات افزونه (با مقدار پیش‌فرض).
	 *
	 * @param string $key     کلید تنظیم.
	 * @param mixed  $default مقدار پیش‌فرض.
	 * @return mixed
	 */
	public static function get_setting( string $key, $default = null ) {
		$settings = get_option( 'bespari_settings', array() );

		return $settings[ $key ] ?? $default;
	}

	/**
	 * ذخیره یک تنظیم.
	 *
	 * @param string $key   کلید.
	 * @param mixed  $value مقدار.
	 */
	public static function update_setting( string $key, $value ): void {
		$settings         = get_option( 'bespari_settings', array() );
		$settings[ $key ] = $value;
		update_option( 'bespari_settings', $settings );
	}

	/**
	 * نام کامل جدول با پیشوند وردپرس.
	 *
	 * @param string $name نام جدول بدون پیشوند.
	 * @return string
	 */
	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . 'bespari_' . $name;
	}

	/**
	 * قالب‌بندی مبلغ به تومان/ریال با جداکننده هزارگان.
	 *
	 * @param float|int $amount مبلغ.
	 * @return string
	 */
	public static function format_money( $amount ): string {
		$currency = self::get_setting( 'currency', 'IRT' );
		$symbol   = 'IRT' === $currency ? ' تومان' : ' ریال';

		return number_format( (float) $amount, 0, '.', ',' ) . $symbol;
	}

	/**
	 * قالب‌بندی درصد.
	 *
	 * @param float $value مقدار درصد.
	 * @return string
	 */
	public static function format_percent( $value ): string {
		return rtrim( rtrim( number_format( (float) $value, 3, '.', '' ), '0' ), '.' ) . '٪';
	}

	/**
	 * دریافت تاریخ و زمان فعلی در فرمت mysql.
	 *
	 * @return string
	 */
	public static function now(): string {
		return current_time( 'mysql' );
	}

	/**
	 * تبدیل تاریخ میلادی به شمسی (نمایشی) — بدون وابستگی به کتابخانه خارجی.
	 *
	 * @param string $mysql_date تاریخ mysql.
	 * @return string
	 */
	public static function to_jalali( string $mysql_date ): string {
		$jdate = self::to_jalali_date( $mysql_date );
		if ( '—' === $jdate ) {
			return '—';
		}

		$ts = strtotime( $mysql_date );

		return $jdate . ' — ' . date( 'H:i', $ts );
	}

	/**
	 * تبدیل تاریخ mysql به شمسی (فقط بخش تاریخ).
	 *
	 * همان تفسیر زمانی `mysql2date('Y/m/d')` را دارد (هر دو از timezone پیش‌فرض PHP
	 * برای تجزیه و قالب‌بندی استفاده می‌کنند)؛ تنها تفاوت تقویم خروجی است.
	 *
	 * @param string $mysql_date تاریخ mysql.
	 * @return string
	 */
	public static function to_jalali_date( string $mysql_date ): string {
		if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
			return '—';
		}

		$ts = strtotime( $mysql_date );
		if ( ! $ts ) {
			return '—';
		}

		list( $jy, $jm, $jd ) = self::gregorian_to_jalali( (int) date( 'Y', $ts ), (int) date( 'n', $ts ), (int) date( 'j', $ts ) );

		return sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
	}

	/**
	 * تبدیل تاریخ میلادی به شمسی.
	 *
	 * @param int $gy سال میلادی.
	 * @param int $gm ماه میلادی.
	 * @param int $gd روز میلادی.
	 * @return array{0:int,1:int,2:int}
	 */
	public static function gregorian_to_jalali( int $gy, int $gm, int $gd ): array {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

		if ( $gy <= 1600 ) {
			$jy  = 0;
			$gy -= 621;
		} else {
			$jy  = 979;
			$gy -= 1600;
		}

		$gy2  = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days = ( 365 * $gy )
			+ (int) ( ( $gy2 + 3 ) / 4 )
			- (int) ( ( $gy2 + 99 ) / 100 )
			+ (int) ( ( $gy2 + 399 ) / 400 )
			- 80
			+ $gd
			+ $g_d_m[ $gm - 1 ];

		$jy   += 33 * (int) ( $days / 12053 );
		$days %= 12053;

		$jy   += 4 * (int) ( $days / 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy   += (int) ( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + (int) ( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + (int) ( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}

	/**
	 * دریافت آی‌پی کاربر.
	 *
	 * @return string
	 */
	public static function client_ip(): string {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );

				return trim( $ip[0] );
			}
		}

		return '';
	}

	/**
	 * تولید اسلاگ یکتا از روی نام (با پشتیبانی فارسی).
	 *
	 * @param string $name    نام.
	 * @param string $table   جدول برای بررسی یکتایی.
	 * @param int    $exclude آی‌دی برای حذف از بررسی (ویرایش).
	 * @return string
	 */
	public static function unique_slug( string $name, string $table, int $exclude = 0 ): string {
		global $wpdb;

		$base = self::slugify( $name );
		if ( '' === $base ) {
			$base = 'item';
		}

		$slug   = $base;
		$i      = 1;
		$full   = self::table( $table );

		while ( true ) {
			$sql    = $exclude
				? $wpdb->prepare( "SELECT COUNT(*) FROM {$full} WHERE slug = %s AND id != %d", $slug, $exclude ) // phpcs:ignore
				: $wpdb->prepare( "SELECT COUNT(*) FROM {$full} WHERE slug = %s", $slug ); // phpcs:ignore
			$exists = (int) $wpdb->get_var( $sql );

			if ( 0 === $exists ) {
				break;
			}

			$slug = $base . '-' . ++$i;
		}

		return $slug;
	}

	/**
	 * ساخت اسلاگ از رشته (فارسی به ترانویزی ساده + انگلیسی).
	 *
	 * @param string $text ورودی.
	 * @return string
	 */
	public static function slugify( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}

		// اگر کاراکتر لاتین دارد از sanitize_title استفاده کن.
		if ( preg_match( '/[a-zA-Z0-9]/', $text ) ) {
			$slug = sanitize_title( $text );
			if ( '' !== $slug ) {
				return $slug;
			}
		}

		// برای متن کاملاً فارسی: ترانویزی ساده.
		$map = array(
			'ا' => 'a', 'آ' => 'a', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
			'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z',
			'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's',
			'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f',
			'ق' => 'gh', 'ک' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
			'و' => 'v', 'ه' => 'h', 'ی' => 'y', 'ة' => 'h', 'أ' => 'a', 'إ' => 'e',
			'‌' => '-', ' ' => '-', 'ٔ' => '', 'ٌ' => '', 'ٍ' => '', 'َ' => '', 'ُ' => '', 'ِ' => '',
		);

		$slug = strtr( $text, $map );
		$slug = strtolower( $slug );
		$slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );

		return trim( $slug, '-' );
	}

	/**
	 * پاک‌سازی و اعتبارسنجی ورودی عددی اعشاری.
	 *
	 * @param mixed $value ورودی.
	 * @return float
	 */
	public static function to_float( $value ): float {
		return (float) str_replace( array( ',', ' ' ), '', (string) $value );
	}

	/**
	 * بررسی فعال بودن ووکامرس.
	 *
	 * @return bool
	 */
	public static function woocommerce_is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * ساخت لینک صفحه ادمین افزونه.
	 *
	 * @param string $page اسلاگ صفحه.
	 * @param array  $args پارامترهای اضافی.
	 * @return string
	 */
	public static function admin_url( string $page, array $args = array() ): string {
		$base = array( 'page' => $page );

		return add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * دریافت nonce فعلی کاربر برای عملیات ادمین.
	 *
	 * @param string $action نام عملیات.
	 * @return string
	 */
	public static function nonce( string $action ): string {
		return wp_create_nonce( 'bespari_' . $action );
	}

	/**
	 * بررسی nonce.
	 *
	 * @param string $action نام عملیات.
	 * @return bool
	 */
	public static function verify_nonce( string $action ): bool {
		$nonce = isset( $_REQUEST['bespari_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bespari_nonce'] ) ) : '';

		return wp_verify_nonce( $nonce, 'bespari_' . $action );
	}

	/**
	 * تولید شماره سفارش یکتا: ORD-YYYYMMDD-XXXX
	 *
	 * @return string
	 */
	public static function generate_order_number(): string {
		global $wpdb;

		$prefix = 'ORD-' . gmdate( 'Ymd' ) . '-';
		$table  = self::table( 'orders' );

		// آخرین شماره امروز را بیاب.
		$like  = $wpdb->esc_like( $prefix ) . '%';
		$sql   = $wpdb->prepare( "SELECT order_number FROM {$table} WHERE order_number LIKE %s ORDER BY id DESC LIMIT 1", $like ); // phpcs:ignore
		$last  = $wpdb->get_var( $sql ); // phpcs:ignore

		$seq = 1;
		if ( $last ) {
			$suffix = substr( (string) $last, strlen( $prefix ) );
			if ( is_numeric( $suffix ) ) {
				$seq = (int) $suffix + 1;
			}
		}

		// حل تداخل هم‌زمانی: حلقه تا یکتایی.
		for ( $i = 0; $i < 20; $i++ ) {
			$candidate = $prefix . str_pad( (string) ( $seq + $i ), 4, '0', STR_PAD_LEFT );
			$exists    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE order_number = %s", $candidate ) ); // phpcs:ignore
			if ( 0 === $exists ) {
				return $candidate;
			}
		}

		// fallback تصادفی.
		return $prefix . str_pad( (string) wp_rand( 1, 9999 ), 4, '0', STR_PAD_LEFT );
	}
}
