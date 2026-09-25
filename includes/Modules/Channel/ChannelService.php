<?php
/**
 * سرویس لاجیک کانال‌های فروش — اعتبارسنجی و تبدیل ورودی به داده دیتابیس.
 *
 * @package Bespari\Modules\Channel
 */

namespace Bespari\Modules\Channel;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChannelService {

	/**
	 * @var ChannelRepository
	 */
	private ChannelRepository $repo;

	public function __construct() {
		$this->repo = new ChannelRepository();
	}

	/**
	 * دریافت همه کانال‌ها.
	 *
	 * @param bool $only_active فقط فعال.
	 * @return ChannelModel[]
	 */
	public function list( bool $only_active = false ): array {
		return $this->repo->get_all( $only_active );
	}

	/**
	 * دریافت یک کانال.
	 *
	 * @param int $id آی‌دی.
	 * @return ChannelModel|null
	 */
	public function get( int $id ): ?ChannelModel {
		return $this->repo->get_by_id( $id );
	}

	/**
	 * ایجاد کانال جدید.
	 *
	 * @param array $input ورودی از فرم.
	 * @return int|WP_Error آی‌دی جدید یا خطا.
	 */
	public function create( array $input ) {
		$data = $this->sanitize( $input );

		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'نام کانال الزامی است.', 'bespari-core' ) );
		}

		$data['slug']       = Helpers::unique_slug( $data['name'], 'channels' );
		$data['created_at'] = Helpers::now();
		$data['updated_at'] = Helpers::now();

		// جدا کردن بازه‌های تسویه.
		$periods = $data['_periods'] ?? array();
		unset( $data['_periods'] );

		$id = $this->repo->insert( $data );

		if ( $id ) {
			if ( ! empty( $periods ) ) {
				$this->repo->save_settlement_periods( $id, $periods );
			}

			\Bespari\Support\AuditLog::created( 'channel', $id, $data );
		}

		return $id ?: new \WP_Error( 'insert_failed', __( 'خطا در ایجاد کانال.', 'bespari-core' ) );
	}

	/**
	 * به‌روزرسانی کانال.
	 *
	 * @param int   $id    آی‌دی.
	 * @param array $input ورودی.
	 * @return bool|WP_Error
	 */
	public function update( int $id, array $input ) {
		$existing = $this->repo->get_by_id( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'کانال یافت نشد.', 'bespari-core' ) );
		}

		$data = $this->sanitize( $input );

		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'نام کانال الزامی است.', 'bespari-core' ) );
		}

		// اگر نام تغییر کرده، اسلاگ را بازتولید کن (مگر اینکه دستی داده شده باشد).
		if ( $data['name'] !== $existing->name ) {
			$data['slug'] = Helpers::unique_slug( $data['name'], 'channels', $id );
		} else {
			unset( $data['slug'] );
		}

		$data['updated_at'] = Helpers::now();

		$periods = $data['_periods'] ?? null;
		unset( $data['_periods'] );

		$old = $existing->to_array();
		$ok  = $this->repo->update( $id, $data );

		if ( $ok && null !== $periods ) {
			$this->repo->save_settlement_periods( $id, $periods );
		}

		if ( $ok ) {
			\Bespari\Support\AuditLog::updated( 'channel', $id, $old, $data );
		}

		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در به‌روزرسانی کانال.', 'bespari-core' ) );
	}

	/**
	 * حذف کانال.
	 *
	 * @param int $id آی‌دی.
	 * @return bool|WP_Error
	 */
	public function delete( int $id ) {
		$existing = $this->repo->get_by_id( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'کانال یافت نشد.', 'bespari-core' ) );
		}

		$ok = $this->repo->delete( $id );

		if ( $ok ) {
			\Bespari\Support\AuditLog::deleted( 'channel', $id, $existing->to_array() );

			// حذف بازه‌های تسویه مرتبط.
			global $wpdb;
			$wpdb->delete( Helpers::table( 'channel_settlement_periods' ), array( 'channel_id' => $id ) );
		}

		return $ok ? true : new \WP_Error( 'delete_failed', __( 'خطا در حذف کانال.', 'bespari-core' ) );
	}

	/**
	 * تغییر وضعیت فعال/غیرفعال.
	 *
	 * @param int $id آی‌دی.
	 * @return bool|WP_Error
	 */
	public function toggle_status( int $id ) {
		$existing = $this->repo->get_by_id( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'کانال یافت نشد.', 'bespari-core' ) );
		}

		$new_status = $existing->status ? 0 : 1;

		return $this->repo->update( $id, array(
			'status'     => $new_status,
			'updated_at' => Helpers::now(),
		) );
	}

	/**
	 * پاک‌سازی و تبدیل ورودی فرم به داده دیتابیس.
	 *
	 * @param array $input ورودی خام.
	 * @return array
	 */
	private function sanitize( array $input ): array {
		$data = array();

		$data['name']       = sanitize_text_field( $input['name'] ?? '' );
		$data['status']     = isset( $input['status'] ) ? (int) $input['status'] : 1;
		$data['is_marketplace'] = isset( $input['is_marketplace'] ) ? (int) $input['is_marketplace'] : 0;

		$data['price_markup_percent']   = Helpers::to_float( $input['price_markup_percent'] ?? 0 );
		$data['credit_markup_percent']  = Helpers::to_float( $input['credit_markup_percent'] ?? 0 );
		$data['commission_percent']     = Helpers::to_float( $input['commission_percent'] ?? 0 );
		$data['processing_fee_percent'] = Helpers::to_float( $input['processing_fee_percent'] ?? 0 );
		$data['shipping_fee_percent']   = Helpers::to_float( $input['shipping_fee_percent'] ?? 0 );
		$data['advertising_fee_percent'] = Helpers::to_float( $input['advertising_fee_percent'] ?? 0 );
		$data['gateway_fee_percent']    = Helpers::to_float( $input['gateway_fee_percent'] ?? 0 );
		$data['fixed_fee']              = Helpers::to_float( $input['fixed_fee'] ?? 0 );
		$data['tax_percent']            = Helpers::to_float( $input['tax_percent'] ?? 0 );
		$data['credit_limit']           = Helpers::to_float( $input['credit_limit'] ?? 0 );

		$data['tax_mode']        = sanitize_key( $input['tax_mode'] ?? 'inclusive' );
		// حالت تسویه — سه حالت جدید؛ مقادیر قدیمی به «فاکتور به فاکتور» نگاشت می‌شوند.
		$mode    = sanitize_key( $input['settlement_mode'] ?? 'invoice' );
		$modes   = array( 'invoice', 'batch', 'statement' );
		$legacy  = array( 'monthly' => 'invoice', 'biweekly' => 'invoice', 'weekly' => 'invoice', 'custom' => 'invoice' );
		if ( isset( $legacy[ $mode ] ) ) {
			$mode = $legacy[ $mode ];
		}
		if ( ! in_array( $mode, $modes, true ) ) {
			$mode = 'invoice';
		}
		$data['settlement_mode'] = $mode;

		// روزهای تسویه: در حالت صورت‌حساب = طول دوره صورت‌حساب؛ در سایر حالت‌ها بدون اثر.
		$data['settlement_days'] = (int) ( $input['settlement_days'] ?? 0 );

		$data['logo']        = esc_url_raw( $input['logo'] ?? '' );
		$data['webhook_url'] = esc_url_raw( $input['webhook_url'] ?? '' );
		$data['description'] = sanitize_textarea_field( $input['description'] ?? '' );
		$data['sort_order']  = (int) ( $input['sort_order'] ?? 0 );

		// خط تولید: بازه ارسال و الویت تولید کانال.
		$data['shipping_window']     = sanitize_text_field( $input['shipping_window'] ?? '' );
		$data['production_priority'] = (int) ( $input['production_priority'] ?? 0 );

		// تنظیمات API به صورت JSON.
		if ( isset( $input['api_settings'] ) && is_array( $input['api_settings'] ) ) {
			$data['api_settings'] = wp_json_encode( $input['api_settings'], JSON_UNESCAPED_UNICODE );
		} elseif ( isset( $input['api_settings'] ) && is_string( $input['api_settings'] ) ) {
			$data['api_settings'] = $input['api_settings'];
		}

		// بازه‌های تسویه (آرایه).
		if ( isset( $input['periods'] ) && is_array( $input['periods'] ) ) {
			$data['_periods'] = $input['periods'];
		}

		return $data;
	}
}
