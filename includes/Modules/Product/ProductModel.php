<?php
/**
 * مدل محصول.
 *
 * @package Bespari\Modules\Product
 */

namespace Bespari\Modules\Product;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductModel {

	public int $id              = 0;
	public string $name         = '';
	public string $sku          = '';
	public string $product_code = '';
	public int $wc_product_id   = 0;
	public int $brand_id        = 0;
	public int $category_id     = 0;
	public float $base_price     = 0.0;
	public float $purchase_price = 0.0;
	public float $finished_cost  = 0.0;
	public int $stock           = 0;
	public int $status          = 1;
	public string $description   = '';
	public ?array $meta         = null;
	public string $created_at    = '';
	public string $updated_at    = '';

	/**
	 * نام برند مرتبط (برای نمایش در جدول).
	 *
	 * @var string
	 */
	public string $brand_name    = '';

	/**
	 * نگاشت‌های کانال (آرایه‌ای از ردیف‌های product_channel).
	 *
	 * @var array
	 */
	public array $channels       = array();

	/**
	 * پر کردن مدل از ردیف دیتابیس.
	 *
	 * @param object|array $row ردیف.
	 * @return static
	 */
	public static function from_row( $row ): self {
		$model = new self();
		$row   = (array) $row;

		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( in_array( $key, array( 'brand_name', 'channels' ), true ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];
			if ( null === $val ) {
				continue;
			}

			if ( 'meta' === $key && is_string( $val ) && '' !== $val ) {
				$decoded     = json_decode( $val, true );
				$model->$key = is_array( $decoded ) ? $decoded : null;
			} else {
				$model->$key = $val;
			}
		}

		$model->id             = (int) $model->id;
		$model->wc_product_id  = (int) $model->wc_product_id;
		$model->brand_id       = (int) $model->brand_id;
		$model->category_id    = (int) $model->category_id;
		$model->base_price     = (float) $model->base_price;
		$model->purchase_price = (float) $model->purchase_price;
		$model->finished_cost  = (float) $model->finished_cost;
		$model->stock          = (int) $model->stock;
		$model->status         = (int) $model->status;

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
	 * محاسبه سود ناخالص (قیمت پایه - قیمت تمام‌شده).
	 *
	 * @return float
	 */
	public function gross_margin(): float {
		$cost = $this->finished_cost > 0 ? $this->finished_cost : $this->purchase_price;

		return $this->base_price - $cost;
	}

	/**
	 * نام وضعیت.
	 *
	 * @return string
	 */
	public function status_label(): string {
		return 1 === $this->status ? __( 'فعال', 'bespari-core' ) : __( 'غیرفعال', 'bespari-core' );
	}
}
