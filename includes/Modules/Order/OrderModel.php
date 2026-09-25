<?php
/**
 * مدل سفارش.
 *
 * @package Bespari\Modules\Order
 */

namespace Bespari\Modules\Order;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderModel {

	public int $id               = 0;
	public string $order_number  = '';
	public int $channel_id       = 0;
	public int $brand_id         = 0;
	public int $seller_id        = 0;
	public string $customer_name    = '';
	public string $customer_phone   = '';
	public string $customer_address = '';
	public string $sale_type     = 'cash';
	public string $status        = 'pending';
	public string $payment_status    = 'unpaid';
	public string $settlement_status = 'pending';
	public ?int $settlement_id   = null;
	public int $production_priority = 0;
	public ?string $production_date = null;
	public int $production_moved = 0;
	public ?string $produced_at  = null;
	public string $ordered_at    = '';
	public ?string $due_at       = null;
	public float $total_gross    = 0.0;
	public float $total_net      = 0.0;
	public float $total_profit   = 0.0;
	public int $rule_version     = 1;
	public ?array $pricing_snapshot    = null;
	public ?array $financials_snapshot = null;
	public string $notes         = '';
	public string $created_at    = '';
	public string $updated_at    = '';

	/** نام‌های جوین‌شده برای نمایش */
	public string $channel_name  = '';
	public string $brand_name    = '';
	public string $seller_name   = '';

	/** آیتم‌ها (پر می‌شود توسط Repository::get_full) */
	public array $items          = array();

	public static function from_row( $row ): self {
		$m   = new self();
		$row = (array) $row;

		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( in_array( $key, array( 'channel_name', 'brand_name', 'seller_name', 'items' ), true ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];
			// فیلدهای nullable می‌توانند null بمانند؛ رشته‌ها را guard می‌کنیم.
			if ( null === $val && ! in_array( $key, array( 'pricing_snapshot', 'financials_snapshot', 'due_at', 'settlement_id', 'production_date', 'produced_at' ), true ) ) {
				continue;
			}
			if ( in_array( $key, array( 'pricing_snapshot', 'financials_snapshot' ), true ) && is_string( $val ) && '' !== $val ) {
				$decoded    = json_decode( $val, true );
				$m->$key    = is_array( $decoded ) ? $decoded : null;
			} else {
				$m->$key = $val;
			}
		}

		$m->id              = (int) $m->id;
		$m->channel_id      = (int) $m->channel_id;
		$m->brand_id        = (int) $m->brand_id;
		$m->seller_id       = (int) $m->seller_id;
		$m->settlement_id   = null !== $m->settlement_id && '' !== $m->settlement_id ? (int) $m->settlement_id : null;
		$m->total_gross     = (float) $m->total_gross;
		$m->total_net       = (float) $m->total_net;
		$m->total_profit    = (float) $m->total_profit;
		$m->rule_version    = (int) $m->rule_version;
		$m->production_priority = (int) $m->production_priority;
		$m->production_moved    = (int) $m->production_moved;

		// جوین‌ها اگر همراه ردیف آمده باشند.
		if ( isset( $row['channel_name'] ) ) {
			$m->channel_name = (string) $row['channel_name'];
		}
		if ( isset( $row['brand_name'] ) ) {
			$m->brand_name = (string) $row['brand_name'];
		}
		if ( isset( $row['seller_name'] ) ) {
			$m->seller_name = (string) $row['seller_name'];
		}

		return $m;
	}

	public function to_array(): array {
		return get_object_vars( $this );
	}

	public function is_editable(): bool {
		return ! in_array( $this->settlement_status, array( 'settled', 'paid' ), true )
			&& 'paid' !== $this->status;
	}

	public function status_label(): string {
		$map = array(
			'pending'   => __( 'در انتظار', 'bespari-core' ),
			'confirmed' => __( 'تاییدشده', 'bespari-core' ),
			'produced'  => __( 'تولید شده', 'bespari-core' ),
			'shipped'   => __( 'ارسال‌شده', 'bespari-core' ),
			'delivered' => __( 'تحویل‌شده', 'bespari-core' ),
			'cancelled' => __( 'لغوشده', 'bespari-core' ),
			'paid'      => __( 'پرداخت‌شده', 'bespari-core' ),
		);
		return $map[ $this->status ] ?? $this->status;
	}

	public function payment_status_label(): string {
		$map = array(
			'unpaid'  => __( 'پرداخت‌نشده', 'bespari-core' ),
			'partial' => __( 'جزئی', 'bespari-core' ),
			'paid'    => __( 'پرداخت‌شده', 'bespari-core' ),
		);
		return $map[ $this->payment_status ] ?? $this->payment_status;
	}

	public function settlement_status_label(): string {
		$map = array(
			'pending' => __( 'در انتظار تسویه', 'bespari-core' ),
			'settled' => __( 'تسویه‌شده', 'bespari-core' ),
			'paid'    => __( 'پرداخت‌شده', 'bespari-core' ),
		);
		return $map[ $this->settlement_status ] ?? $this->settlement_status;
	}

	public function sale_type_label(): string {
		return 'credit' === $this->sale_type ? __( 'اعتباری', 'bespari-core' ) : __( 'نقدی', 'bespari-core' );
	}
}
