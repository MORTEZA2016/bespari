<?php
/**
 * سرویس سایت‌های اقماری.
 *
 * @package Bespari\Modules\Agent
 */

namespace Bespari\Modules\Agent;

use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgentService {

	private AgentRepository $repo;

	public function __construct() {
		$this->repo = new AgentRepository();
	}

	/**
	 * لیست همه agentها.
	 *
	 * @return AgentModel[]
	 */
	public function list(): array {
		return $this->repo->all();
	}

	public function get( int $id ): ?AgentModel {
		return $this->repo->get_by_id( $id );
	}

	/**
	 * ایجاد agent جدید با تولید api_key/secret.
	 *
	 * @return array{agent_id: int, api_key: string, api_secret: string}|\WP_Error
	 */
	public function create( array $input ) {
		$name     = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
		$site_url = isset( $input['site_url'] ) ? esc_url_raw( $input['site_url'] ) : '';
		$channel  = (int) ( $input['channel_id'] ?? 0 );
		$seller   = (int) ( $input['seller_id'] ?? 0 );

		if ( '' === $name ) {
			return new \WP_Error( 'missing_name', __( 'نام اتصال الزامی است.', 'bespari-core' ) );
		}
		if ( '' === $site_url || ! wp_http_validate_url( $site_url ) ) {
			return new \WP_Error( 'invalid_url', __( 'آدرس سایت معتبر نیست.', 'bespari-core' ) );
		}
		if ( $channel <= 0 ) {
			return new \WP_Error( 'missing_channel', __( 'کانال اختصاصی الزامی است.', 'bespari-core' ) );
		}

		// تولید کلید یکتا و secret تصادفی.
		do {
			$api_key = bin2hex( random_bytes( 16 ) ); // 32 hex.
		} while ( $this->repo->api_key_exists( $api_key ) );

		$api_secret = bin2hex( random_bytes( 32 ) ); // 64 hex.

		$now = Helpers::now();
		$id  = $this->repo->insert( array(
			'name'       => $name,
			'site_url'   => $site_url,
			'api_key'    => $api_key,
			'api_secret' => $api_secret,
			'channel_id' => $channel,
			'seller_id'  => $seller,
			'status'     => 'active',
			'created_at' => $now,
			'updated_at' => $now,
		) );

		if ( ! $id ) {
			return new \WP_Error( 'insert_failed', __( 'خطا در ایجاد اتصال.', 'bespari-core' ) );
		}

		// AuditLog بدون secret.
		AuditLog::created( 'agent', $id, array(
			'name'       => $name,
			'site_url'   => $site_url,
			'api_key'    => $api_key,
			'channel_id' => $channel,
			'seller_id'  => $seller,
		) );

		return array(
			'agent_id'   => $id,
			'api_key'    => $api_key,
			'api_secret' => $api_secret,
		);
	}

	/**
	 * ویرایش agent (بدون تغییر کلید/secret).
	 */
	public function update( int $id, array $input ) {
		$existing = $this->repo->get_by_id( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'اتصال یافت نشد.', 'bespari-core' ) );
		}

		$row = array( 'updated_at' => Helpers::now() );

		if ( isset( $input['name'] ) && '' !== sanitize_text_field( $input['name'] ) ) {
			$row['name'] = sanitize_text_field( $input['name'] );
		}
		if ( isset( $input['site_url'] ) ) {
			$site_url = esc_url_raw( $input['site_url'] );
			if ( ! wp_http_validate_url( $site_url ) ) {
				return new \WP_Error( 'invalid_url', __( 'آدرس سایت معتبر نیست.', 'bespari-core' ) );
			}
			$row['site_url'] = $site_url;
		}
		if ( isset( $input['channel_id'] ) ) {
			$row['channel_id'] = (int) $input['channel_id'];
		}
		if ( isset( $input['seller_id'] ) ) {
			$row['seller_id'] = (int) $input['seller_id'];
		}
		if ( isset( $input['status'] ) && in_array( $input['status'], array( 'active', 'inactive', 'blocked' ), true ) ) {
			$row['status'] = sanitize_key( $input['status'] );
		}

		$ok = $this->repo->update( $id, $row );
		if ( ! $ok ) {
			return new \WP_Error( 'update_failed', __( 'خطا در ویرایش اتصال.', 'bespari-core' ) );
		}

		AuditLog::updated( 'agent', $id, array( 'name' => $existing->name, 'status' => $existing->status ), array_intersect_key( $row, array_flip( array( 'name', 'status' ) ) ) );

		return true;
	}

	/**
	 * چرخش secret (کلید ثابت می‌ماند).
	 *
	 * @return string|\WP_Error secret جدید (فقط یک بار برگردانده می‌شود)
	 */
	public function rotate_secret( int $id ) {
		$existing = $this->repo->get_by_id( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'اتصال یافت نشد.', 'bespari-core' ) );
		}

		$api_secret = bin2hex( random_bytes( 32 ) );
		$ok         = $this->repo->update( $id, array( 'api_secret' => $api_secret, 'updated_at' => Helpers::now() ) );
		if ( ! $ok ) {
			return new \WP_Error( 'update_failed', __( 'خطا در چرخش secret.', 'bespari-core' ) );
		}

		AuditLog::log( 'rotate_secret', 'agent', $id, null, array( 'note' => 'secret rotated' ) );

		return $api_secret;
	}

	/**
	 * حذف agent.
	 */
	public function delete( int $id ) {
		$existing = $this->repo->get_by_id( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'اتصال یافت نشد.', 'bespari-core' ) );
		}

		$ok = $this->repo->delete( $id );
		if ( ! $ok ) {
			return new \WP_Error( 'delete_failed', __( 'خطا در حذف اتصال.', 'bespari-core' ) );
		}

		AuditLog::deleted( 'agent', $id, array( 'name' => $existing->name, 'api_key' => $existing->api_key ) );

		return true;
	}

	/**
	 * تایید HMAC یک درخواست REST.
	 *
	 * @param string $api_key    X-Bespari-Key.
	 * @param string $timestamp  X-Bespari-Timestamp (unix).
	 * @param string $signature  X-Bespari-Signature.
	 * @param string $raw_body   بدنه خام درخواست.
	 * @return AgentModel|\WP_Error
	 */
	public function verify_request( string $api_key, string $timestamp, string $signature, string $raw_body ) {
		// اعتبار کلید.
		$agent = $this->repo->find_by_api_key( sanitize_text_field( $api_key ) );
		if ( ! $agent ) {
			return new \WP_Error( 'auth_failed', __( 'احراز هویت نامعتبر است.', 'bespari-core' ), array( 'status' => 401 ) );
		}

		if ( 'active' !== $agent->status ) {
			return new \WP_Error( 'agent_inactive', __( 'اتصال غیرفعال است.', 'bespari-core' ), array( 'status' => 403 ) );
		}

		// پنجره زمانی ۳۰۰ ثانیه (ضد replay).
		$ts = (int) $timestamp;
		if ( $ts <= 0 || abs( time() - $ts ) > 300 ) {
			return new \WP_Error( 'auth_failed', __( 'احراز هویت نامعتبر است.', 'bespari-core' ), array( 'status' => 401 ) );
		}

		// امضا.
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $agent->api_secret );
		if ( ! hash_equals( $expected, (string) $signature ) ) {
			return new \WP_Error( 'auth_failed', __( 'احراز هویت نامعتبر است.', 'bespari-core' ), array( 'status' => 401 ) );
		}

		return $agent;
	}

	/**
	 * محدودیت نرخ سبک per api_key (پیش‌فرض ۶۰/دقیقه).
	 *
	 * @return bool اجازه دارد؟
	 */
	public function check_rate_limit( string $api_key ): bool {
		$limit = (int) Helpers::get_setting( 'agent_rate_limit', 60 );
		if ( $limit <= 0 ) {
			return true;
		}

		$transient = 'bespari_rl_' . md5( $api_key );
		$count     = (int) get_transient( $transient );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $transient, $count + 1, 60 );
		return true;
	}

	/**
	 * ثبت لاگ + آپدیت last_sync.
	 */
	public function record_activity( int $agent_id, string $direction, string $endpoint, string $status, string $message = '' ): void {
		$this->repo->log( $agent_id, $direction, $endpoint, $status, $message );

		if ( 'ok' === $status ) {
			$this->repo->update( $agent_id, array( 'last_sync_at' => Helpers::now(), 'updated_at' => Helpers::now() ) );
		}
	}

	public function get_logs( int $agent_id, int $limit = 20 ): array {
		return $this->repo->get_logs( $agent_id, $limit );
	}
}
