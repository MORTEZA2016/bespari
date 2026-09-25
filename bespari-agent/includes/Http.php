<?php
/**
 * Http — درخواست امضاشده HMAC به سایت مادر.
 *
 * @package BespariAgent
 */

namespace BespariAgent;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Http {

	/**
	 * تنظیمات افزونه.
	 */
	public static function get_settings(): array {
		return wp_parse_args( get_option( 'bespari_agent_settings', array() ), array(
			'enabled'   => 0,
			'site_url'  => '',
			'api_key'   => '',
			'api_secret' => '',
		) );
	}

	/**
	 * آیا افزونه پیکربندی و فعال است؟
	 */
	public static function is_ready(): bool {
		$settings = self::get_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['site_url'] ) && ! empty( $settings['api_key'] ) && ! empty( $settings['api_secret'] );
	}

	/**
	 * امضای HMAC-SHA256 برای بدنه.
	 */
	public static function sign( string $timestamp, string $body, string $secret ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	/**
	 * ارسال درخواست امضاشده به سایت مادر.
	 *
	 * @param string $method    POST|GET.
	 * @param string $path      مسیر نسبت به /wp-json/ مثل bespari/v1/agent/handshake.
	 * @param array  $body      بدنه (برای POST).
	 * @return array{ok: bool, data: array, error: string}
	 */
	public static function signed_request( string $method, string $path, array $body = array() ): array {
		$settings = self::get_settings();

		if ( empty( $settings['site_url'] ) ) {
			return array( 'ok' => false, 'data' => array(), 'error' => 'آدرس سایت مادر تنظیم نشده است.' );
		}

		$base      = untrailingslashit( esc_url_raw( $settings['site_url'] ) );
		$url       = $base . '/wp-json/' . ltrim( $path, '/' );
		$timestamp = (string) time();
		$raw_body  = '' ;

		if ( 'POST' === $method ) {
			$raw_body = (string) wp_json_encode( $body, JSON_UNESCAPED_UNICODE );
		}

		// برای GET: پارامترها در URL، امضا روی timestamp + '' (بدنه خالی).
		$signature = self::sign( $timestamp, $raw_body, (string) $settings['api_secret'] );

		$response = wp_remote_request( $url, array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Content-Type'           => 'application/json; charset=utf-8',
				'X-Bespari-Key'          => (string) $settings['api_key'],
				'X-Bespari-Timestamp'    => $timestamp,
				'X-Bespari-Signature'    => $signature,
			),
			'body'    => 'POST' === $method ? $raw_body : null,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'data' => array(), 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
			return array(
				'ok'    => false,
				'data'  => is_array( $json ) ? $json : array(),
				'error' => sprintf( 'پاسخ HTTP %d از سایت مادر.', $code ),
			);
		}

		return array(
			'ok'    => ! empty( $json['ok'] ),
			'data'  => $json,
			'error' => isset( $json['message'] ) ? (string) $json['message'] : '',
		);
	}

	/**
	 * ثبت لاگ محلی (option، حداکثر ۱۰۰ رکورد).
	 */
	public static function log( string $direction, string $endpoint, string $status, string $message = '' ): void {
		$logs = get_option( 'bespari_agent_log', array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		array_unshift( $logs, array(
			'direction'  => $direction,
			'endpoint'   => $endpoint,
			'status'     => $status,
			'message'    => $message,
			'time'       => current_time( 'mysql' ),
		) );

		$logs = array_slice( $logs, 0, 100 );
		update_option( 'bespari_agent_log', $logs );
	}
}
