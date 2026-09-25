<?php
/**
 * سرویس لاجیک قوانین قیمت‌گذاری.
 *
 * @package Bespari\Modules\Pricing
 */

namespace Bespari\Modules\Pricing;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PricingRuleService {

	private PricingRepository $repo;
	private PricingEngine $engine;

	public function __construct() {
		$this->repo   = new PricingRepository();
		$this->engine = new PricingEngine();
	}

	/**
	 * لیست همه قوانین.
	 *
	 * @param bool $only_active فقط فعال.
	 * @return PricingRuleModel[]
	 */
	public function list( bool $only_active = false ): array {
		return $this->repo->get_all_ordered( $only_active );
	}

	/**
	 * دریافت یک قانون.
	 *
	 * @param int $id آی‌دی.
	 * @return PricingRuleModel|null
	 */
	public function get( int $id ): ?PricingRuleModel {
		return $this->repo->get_by_id( $id );
	}

	/**
	 * ایجاد قانون.
	 *
	 * @param array $input ورودی.
	 * @return int|WP_Error
	 */
	public function create( array $input ) {
		$data = $this->sanitize( $input );

		if ( empty( $data['title'] ) ) {
			return new \WP_Error( 'missing_title', __( 'عنوان قانون الزامی است.', 'bespari-core' ) );
		}

		$data['created_at'] = Helpers::now();
		$data['updated_at'] = Helpers::now();

		$id = $this->repo->insert( $data );

		if ( $id ) {
			\Bespari\Support\AuditLog::created( 'pricing_rule', $id, $data );
			$this->engine->bump_version();
		}

		return $id ?: new \WP_Error( 'insert_failed', __( 'خطا در ایجاد قانون.', 'bespari-core' ) );
	}

	/**
	 * به‌روزرسانی قانون.
	 *
	 * @param int   $id    آی‌دی.
	 * @param array $input ورودی.
	 * @return bool|WP_Error
	 */
	public function update( int $id, array $input ) {
		$existing = $this->repo->get_by_id( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'قانون یافت نشد.', 'bespari-core' ) );
		}

		$data = $this->sanitize( $input );

		if ( empty( $data['title'] ) ) {
			return new \WP_Error( 'missing_title', __( 'عنوان قانون الزامی است.', 'bespari-core' ) );
		}

		// افزایش نسخه قانون برای حفظ ثبات محاسبات قدیمی.
		$data['rule_version'] = $existing->rule_version + 1;
		$data['updated_at']   = Helpers::now();

		$ok = $this->repo->update( $id, $data );

		if ( $ok ) {
			\Bespari\Support\AuditLog::updated( 'pricing_rule', $id, $existing->to_array(), $data );
			$this->engine->bump_version();
		}

		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در به‌روزرسانی قانون.', 'bespari-core' ) );
	}

	/**
	 * حذف قانون.
	 *
	 * @param int $id آی‌دی.
	 * @return bool|WP_Error
	 */
	public function delete( int $id ) {
		$existing = $this->repo->get_by_id( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'قانون یافت نشد.', 'bespari-core' ) );
		}

		$ok = $this->repo->delete( $id );

		if ( $ok ) {
			\Bespari\Support\AuditLog::deleted( 'pricing_rule', $id, $existing->to_array() );
			$this->engine->bump_version();
		}

		return $ok ? true : new \WP_Error( 'delete_failed', __( 'خطا در حذف قانون.', 'bespari-core' ) );
	}

	/**
	 * پیش‌نمایش محاسبه قیمت برای یک محصول + کانال + نوع فروش.
	 *
	 * @param int    $product_id آی‌دی محصول.
	 * @param int    $channel_id آی‌دی کانال.
	 * @param string $sale_type  نوع فروش.
	 * @param int    $quantity   تعداد.
	 * @return array|WP_Error
	 */
	public function preview( int $product_id, int $channel_id, string $sale_type = 'cash', int $quantity = 1 ) {
		$product_repo = new \Bespari\Modules\Product\ProductRepository();
		$channel_repo = new \Bespari\Modules\Channel\ChannelRepository();

		$product = $product_repo->get_full( $product_id );
		$channel = $channel_repo->get_by_id( $channel_id );

		if ( ! $product ) {
			return new \WP_Error( 'product_not_found', __( 'محصول یافت نشد.', 'bespari-core' ) );
		}
		if ( ! $channel ) {
			return new \WP_Error( 'channel_not_found', __( 'کانال یافت نشد.', 'bespari-core' ) );
		}

		return $this->engine->calculate( $product, $channel, $sale_type, 0, $quantity );
	}

	/**
	 * پاک‌سازی ورودی.
	 *
	 * @param array $input ورودی خام.
	 * @return array
	 */
	private function sanitize( array $input ): array {
		$data = array();

		$data['title']        = sanitize_text_field( $input['title'] ?? '' );
		$data['scope']        = sanitize_key( $input['scope'] ?? 'channel' );
		$data['scope_id']     = (int) ( $input['scope_id'] ?? 0 );
		$data['channel_id']   = (int) ( $input['channel_id'] ?? 0 );
		$data['brand_id']     = (int) ( $input['brand_id'] ?? 0 );
		$data['category_id']  = (int) ( $input['category_id'] ?? 0 );
		$data['product_id']   = (int) ( $input['product_id'] ?? 0 );
		$data['seller_id']    = (int) ( $input['seller_id'] ?? 0 );

		$data['adjustment_type']  = in_array( $input['adjustment_type'] ?? 'percent', array( 'percent', 'fixed' ), true )
			? $input['adjustment_type']
			: 'percent';
		$data['adjustment_value'] = Helpers::to_float( $input['adjustment_value'] ?? 0 );

		$data['sale_type'] = in_array( $input['sale_type'] ?? 'all', array( 'all', 'cash', 'credit' ), true )
			? $input['sale_type']
			: 'all';

		$data['priority']  = (int) ( $input['priority'] ?? 10 );
		$data['stackable'] = isset( $input['stackable'] ) ? (int) $input['stackable'] : 1;
		$data['is_active'] = isset( $input['is_active'] ) ? (int) $input['is_active'] : 1;

		$data['valid_from'] = ! empty( $input['valid_from'] ) ? sanitize_text_field( $input['valid_from'] ) : null;
		$data['valid_to']   = ! empty( $input['valid_to'] ) ? sanitize_text_field( $input['valid_to'] ) : null;

		return $data;
	}
}
