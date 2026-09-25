<?php
/**
 * مدل مرجوعی.
 *
 * @package Bespari\Modules\Returns
 */

namespace Bespari\Modules\Returns;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReturnModel {

	public int $id            = 0;
	public int $order_id       = 0;
	public int $product_id     = 0;
	public int $quantity      = 1;
	public string $reason     = '';
	public float $refund_amount = 0.0;
	public string $status     = 'pending';
	public ?string $restocked_at = null;
	public string $notes      = '';
	public string $created_at  = '';
	public string $updated_at  = '';

	/** نام‌های جوین‌شده برای نمایش */
	public string $order_number  = '';
	public string $product_name  = '';
	public string $sku           = '';

	public static function from_row( $row ): self {
		$m   = new self();
		$row = (array) $row;

		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( in_array( $key, array( 'order_number', 'product_name', 'sku' ), true ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];
			// فیلدهای nullable می‌توانند null بمانند؛ رشته‌ها را guard می‌کنیم.
			if ( null === $val && ! in_array( $key, array( 'restocked_at', 'notes' ), true ) ) {
				continue;
			}
			$m->$key = $val;
		}

		$m->id            = (int) $m->id;
		$m->order_id      = (int) $m->order_id;
		$m->product_id    = (int) $m->product_id;
		$m->quantity      = (int) $m->quantity;
		$m->refund_amount = (float) $m->refund_amount;

		// جوین‌ها اگر همراه ردیف آمده باشند.
		if ( isset( $row['order_number'] ) ) {
			$m->order_number = (string) $row['order_number'];
		}
		if ( isset( $row['product_name'] ) ) {
			$m->product_name = (string) $row['product_name'];
		}
		if ( isset( $row['sku'] ) ) {
			$m->sku = (string) $row['sku'];
		}

		return $m;
	}

	public function to_array(): array {
		return get_object_vars( $this );
	}

	public function status_label(): string {
		$map = array(
			'pending'   => __( 'در انتظار', 'bespari-core' ),
			'approved'  => __( 'تاییدشده', 'bespari-core' ),
			'rejected'  => __( 'ردشده', 'bespari-core' ),
			'restocked' => __( 'بازگشت به انبار', 'bespari-core' ),
		);
		return $map[ $this->status ] ?? $this->status;
	}
}
