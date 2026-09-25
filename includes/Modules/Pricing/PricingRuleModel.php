<?php
/**
 * مدل قانون قیمت‌گذاری.
 *
 * @package Bespari\Modules\Pricing
 */

namespace Bespari\Modules\Pricing;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PricingRuleModel {

	public int $id              = 0;
	public string $title        = '';
	public string $scope        = 'channel';
	public int $scope_id        = 0;
	public int $channel_id      = 0;
	public int $brand_id        = 0;
	public int $category_id     = 0;
	public int $product_id      = 0;
	public int $seller_id       = 0;
	public string $adjustment_type  = 'percent';
	public float $adjustment_value  = 0.0;
	public string $sale_type    = 'all';
	public int $priority        = 10;
	public int $stackable       = 1;
	public string $valid_from   = '';
	public string $valid_to     = '';
	public int $is_active       = 1;
	public int $rule_version    = 1;
	public string $created_at    = '';
	public string $updated_at    = '';

	/**
	 * پر کردن از ردیف دیتابیس.
	 *
	 * @param object|array $row ردیف.
	 * @return static
	 */
	public static function from_row( $row ): self {
		$model = new self();
		$row   = (array) $row;

		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			if ( null === $row[ $key ] ) {
				continue;
			}
			$model->$key = $row[ $key ];
		}

		$model->id               = (int) $model->id;
		$model->scope_id         = (int) $model->scope_id;
		$model->channel_id       = (int) $model->channel_id;
		$model->brand_id         = (int) $model->brand_id;
		$model->category_id      = (int) $model->category_id;
		$model->product_id       = (int) $model->product_id;
		$model->seller_id        = (int) $model->seller_id;
		$model->adjustment_value = (float) $model->adjustment_value;
		$model->priority         = (int) $model->priority;
		$model->stackable        = (int) $model->stackable;
		$model->is_active        = (int) $model->is_active;
		$model->rule_version     = (int) $model->rule_version;

		return $model;
	}

	/**
	 * تبدیل به آرایه.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return get_object_vars( $this );
	}

	/**
	 * آیا قانون در تاریخ امروز معتبر است؟
	 *
	 * @return bool
	 */
	public function is_in_valid_period(): bool {
		$today = date( 'Y-m-d' );

		if ( ! empty( $this->valid_from ) && $this->valid_from > $today ) {
			return false;
		}

		if ( ! empty( $this->valid_to ) && $this->valid_to < $today ) {
			return false;
		}

		return true;
	}

	/**
	 * برچسب نوع تعدیل.
	 *
	 * @return string
	 */
	public function adjustment_type_label(): string {
		$map = array(
			'percent' => __( 'درصدی', 'bespari-core' ),
			'fixed'   => __( 'مبلغ ثابت', 'bespari-core' ),
		);

		return $map[ $this->adjustment_type ] ?? $this->adjustment_type;
	}

	/**
	 * برچسب سطح قانون.
	 *
	 * @return string
	 */
	public function scope_label(): string {
		$map = array(
			'channel'  => __( 'کانال', 'bespari-core' ),
			'brand'    => __( 'برند', 'bespari-core' ),
			'category' => __( 'دسته‌بندی', 'bespari-core' ),
			'product'  => __( 'محصول خاص', 'bespari-core' ),
			'seller'   => __( 'بازاریاب', 'bespari-core' ),
		);

		return $map[ $this->scope ] ?? $this->scope;
	}

	/**
	 * برچسب نوع فروش.
	 *
	 * @return string
	 */
	public function sale_type_label(): string {
		$map = array(
			'all'    => __( 'همه', 'bespari-core' ),
			'cash'   => __( 'نقدی', 'bespari-core' ),
			'credit' => __( 'اعتباری', 'bespari-core' ),
		);

		return $map[ $this->sale_type ] ?? $this->sale_type;
	}
}
