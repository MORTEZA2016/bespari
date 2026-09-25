<?php
/**
 * مدل برند.
 *
 * @package Bespari\Modules\Brand
 */

namespace Bespari\Modules\Brand;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BrandModel {

	public int $id              = 0;
	public string $name         = '';
	public string $slug         = '';
	public string $logo         = '';
	public int $owner_user_id   = 0;
	public int $status          = 1;
	public string $description  = '';
	public ?array $meta         = null;
	public string $created_at   = '';
	public string $updated_at   = '';

	/**
	 * @var int[] آی‌دی کانال‌های فعال برند (از جدول brand_channels).
	 */
	public array $channel_ids   = array();

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
			if ( 'channel_ids' === $key || ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];
			if ( null === $val ) {
				continue;
			}

			if ( 'meta' === $key && is_string( $val ) && '' !== $val ) {
				$decoded = json_decode( $val, true );
				$model->$key = is_array( $decoded ) ? $decoded : null;
			} else {
				$model->$key = $val;
			}
		}

		$model->id            = (int) $model->id;
		$model->owner_user_id = (int) $model->owner_user_id;
		$model->status        = (int) $model->status;

		return $model;
	}

	/**
	 * تبدیل به آرایه.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$vars = get_object_vars( $this );

		return $vars;
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
