<?php
/**
 * مدل کانال فروش (Value Object).
 *
 * @package Bespari\Modules\Channel
 */

namespace Bespari\Modules\Channel;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChannelModel {

	public int $id              = 0;
	public string $name         = '';
	public string $slug         = '';
	public string $logo         = '';
	public int $status          = 1;
	public int $is_marketplace  = 0;
	public float $price_markup_percent  = 0.0;
	public float $credit_markup_percent = 0.0;
	public float $commission_percent    = 0.0;
	public float $processing_fee_percent = 0.0;
	public float $shipping_fee_percent   = 0.0;
	public float $advertising_fee_percent = 0.0;
	public float $gateway_fee_percent    = 0.0;
	public float $fixed_fee      = 0.0;
	public string $tax_mode      = 'inclusive';
	public float $tax_percent    = 0.0;
	public string $settlement_mode = 'invoice';
	public int $settlement_days  = 0;
	public float $credit_limit   = 0.0;
	public ?array $api_settings  = null;
	public string $webhook_url   = '';
	public string $shipping_window     = '';
	public int $production_priority    = 0;
	public string $description   = '';
	public int $sort_order       = 0;
	public string $created_at    = '';
	public string $updated_at    = '';

	/**
	 * پر کردن مدل از ردیف دیتابیس.
	 *
	 * @param object|array $row ردیف دیتابیس.
	 * @return static
	 */
	public static function from_row( $row ): self {
		$model = new self();
		$row   = (array) $row;

		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];

			if ( null === $val ) {
				continue;
			}
			if ( 'api_settings' === $key && is_string( $val ) && '' !== $val ) {
				$decoded = json_decode( $val, true );
				$model->$key = is_array( $decoded ) ? $decoded : null;
			} else {
				$model->$key = $val;
			}
		}

		// تبدیل نوع عددی.
		$model->id                   = (int) $model->id;
		$model->status               = (int) $model->status;
		$model->is_marketplace       = (int) $model->is_marketplace;
		$model->price_markup_percent  = (float) $model->price_markup_percent;
		$model->credit_markup_percent = (float) $model->credit_markup_percent;
		$model->commission_percent    = (float) $model->commission_percent;
		$model->processing_fee_percent = (float) $model->processing_fee_percent;
		$model->shipping_fee_percent   = (float) $model->shipping_fee_percent;
		$model->advertising_fee_percent = (float) $model->advertising_fee_percent;
		$model->gateway_fee_percent  = (float) $model->gateway_fee_percent;
		$model->fixed_fee            = (float) $model->fixed_fee;
		$model->tax_percent          = (float) $model->tax_percent;
		$model->settlement_days      = (int) $model->settlement_days;
		$model->credit_limit         = (float) $model->credit_limit;
		$model->sort_order           = (int) $model->sort_order;
		$model->production_priority  = (int) $model->production_priority;

		return $model;
	}

	/**
	 * تبدیل به آرایه برای نمایش یا لاگ.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return get_object_vars( $this );
	}

	/**
	 * نام وضعیت.
	 *
	 * @return string
	 */
	public function status_label(): string {
		return 1 === $this->status ? __( 'فعال', 'bespari-core' ) : __( 'غیرفعال', 'bespari-core' );
	}

	/**
	 * ترجمه حالت تسویه.
	 *
	 * @return string
	 */
	public function settlement_label(): string {
		$map = array(
			'invoice'   => __( 'فاکتور به فاکتور', 'bespari-core' ),
			'batch'     => __( 'محموله به محموله', 'bespari-core' ),
			'statement' => __( 'صورت‌حساب به صورت‌حساب', 'bespari-core' ),
			// نگاشت مقادیر قدیمی (سازگاری رکوردهای قبل از بازطراحی).
			'monthly'   => __( 'فاکتور به فاکتور', 'bespari-core' ),
			'biweekly'  => __( 'فاکتور به فاکتور', 'bespari-core' ),
			'weekly'    => __( 'فاکتور به فاکتور', 'bespari-core' ),
			'custom'    => __( 'فاکتور به فاکتور', 'bespari-core' ),
		);

		return $map[ $this->settlement_mode ] ?? $this->settlement_mode;
	}
}
