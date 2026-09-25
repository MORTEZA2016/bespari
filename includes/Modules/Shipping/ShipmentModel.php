<?php
/**
 * مدل برگه ارسال.
 *
 * @package Bespari\Modules\Shipping
 */

namespace Bespari\Modules\Shipping;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShipmentModel {

	public int $id            = 0;
	public int $order_id       = 0;
	public string $tracking_code = '';
	public string $carrier    = '';
	public string $status     = 'pending';
	public ?string $shipped_at   = null;
	public ?string $delivered_at = null;
	public string $notes      = '';
	public string $created_at  = '';
	public string $updated_at  = '';

	/** نام‌های جوین‌شده برای نمایش */
	public string $order_number     = '';
	public string $customer_name    = '';
	public string $customer_phone   = '';
	public string $customer_address = '';

	public static function from_row( $row ): self {
		$m   = new self();
		$row = (array) $row;

		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( in_array( $key, array( 'order_number', 'customer_name', 'customer_phone', 'customer_address' ), true ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];
			// فیلدهای nullable می‌توانند null بمانند؛ رشته‌ها را guard می‌کنیم.
			if ( null === $val && ! in_array( $key, array( 'shipped_at', 'delivered_at', 'notes' ), true ) ) {
				continue;
			}
			$m->$key = $val;
		}

		$m->id       = (int) $m->id;
		$m->order_id = (int) $m->order_id;

		// جوین‌ها اگر همراه ردیف آمده باشند.
		if ( isset( $row['order_number'] ) ) {
			$m->order_number = (string) $row['order_number'];
		}
		if ( isset( $row['customer_name'] ) ) {
			$m->customer_name = (string) $row['customer_name'];
		}
		if ( isset( $row['customer_phone'] ) ) {
			$m->customer_phone = (string) $row['customer_phone'];
		}
		if ( isset( $row['customer_address'] ) ) {
			$m->customer_address = (string) $row['customer_address'];
		}

		return $m;
	}

	public function to_array(): array {
		return get_object_vars( $this );
	}

	public function status_label(): string {
		$map = array(
			'pending'    => __( 'در انتظار آماده‌سازی', 'bespari-core' ),
			'prepared'   => __( 'آماده ارسال', 'bespari-core' ),
			'shipped'    => __( 'ارسال‌شده', 'bespari-core' ),
			'delivered'  => __( 'تحویل‌شده', 'bespari-core' ),
			'failed'     => __( 'ناموفق', 'bespari-core' ),
			'cancelled'  => __( 'لغوشده', 'bespari-core' ),
		);
		return $map[ $this->status ] ?? $this->status;
	}
}
