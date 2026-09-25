<?php
/**
 * مدل گردش موجودی انبار.
 *
 * @package Bespari\Modules\Warehouse
 */

namespace Bespari\Modules\Warehouse;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StockMovementModel {

	public int $id           = 0;
	public int $product_id    = 0;
	public string $type      = 'in';
	public int $quantity     = 0;
	public int $stock_before = 0;
	public int $stock_after  = 0;
	public string $ref_type  = '';
	public int $ref_id        = 0;
	public string $notes     = '';
	public int $user_id       = 0;
	public string $created_at = '';

	/** نام‌های جوین‌شده برای نمایش */
	public string $product_name = '';
	public string $sku          = '';
	public string $user_name    = '';

	public static function from_row( $row ): self {
		$m   = new self();
		$row = (array) $row;

		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( in_array( $key, array( 'product_name', 'sku', 'user_name' ), true ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];
			// فیلدهای nullable می‌توانند null بمانند؛ رشته‌ها را guard می‌کنیم.
			if ( null === $val && ! in_array( $key, array( 'notes' ), true ) ) {
				continue;
			}
			$m->$key = $val;
		}

		$m->id           = (int) $m->id;
		$m->product_id   = (int) $m->product_id;
		$m->quantity     = (int) $m->quantity;
		$m->stock_before = (int) $m->stock_before;
		$m->stock_after  = (int) $m->stock_after;
		$m->ref_id       = (int) $m->ref_id;
		$m->user_id      = (int) $m->user_id;

		// جوین‌ها اگر همراه ردیف آمده باشند.
		if ( isset( $row['product_name'] ) ) {
			$m->product_name = (string) $row['product_name'];
		}
		if ( isset( $row['sku'] ) ) {
			$m->sku = (string) $row['sku'];
		}
		if ( isset( $row['user_name'] ) ) {
			$m->user_name = (string) $row['user_name'];
		}

		return $m;
	}

	public function to_array(): array {
		return get_object_vars( $this );
	}

	public function type_label(): string {
		$map = array(
			'in'     => __( 'ورود', 'bespari-core' ),
			'out'    => __( 'خروج', 'bespari-core' ),
			'adjust' => __( 'تعدیل', 'bespari-core' ),
		);
		return $map[ $this->type ] ?? $this->type;
	}

	public function ref_label(): string {
		$map = array(
			'order'  => __( 'سفارش', 'bespari-core' ),
			'return' => __( 'مرجوعی', 'bespari-core' ),
			'manual' => __( 'دستی', 'bespari-core' ),
			''       => '—',
		);
		return $map[ $this->ref_type ] ?? $this->ref_type;
	}
}
