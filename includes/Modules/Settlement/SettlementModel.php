<?php
/**
 * مدل تسویه.
 *
 * @package Bespari\Modules\Settlement
 */

namespace Bespari\Modules\Settlement;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettlementModel {

	public int $id              = 0;
	public int $channel_id      = 0;
	public ?int $period_id      = null;
	public string $title        = '';
	public string $period_start = '';
	public string $period_end   = '';
	public string $status       = 'pending';
	public int $total_orders    = 0;
	public float $total_gross      = 0.0;
	public float $total_deductions = 0.0;
	public float $total_net        = 0.0;
	public float $total_profit     = 0.0;
	public ?string $paid_at     = null;
	public string $notes        = '';
	public string $created_at   = '';
	public string $updated_at   = '';

	public string $channel_name = '';

	public static function from_row( $row ): self {
		$m   = new self();
		$row = (array) $row;
		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( 'channel_name' === $key ) {
				continue;
			}
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			if ( null === $row[ $key ] && ! in_array( $key, array( 'period_id', 'paid_at' ), true ) ) {
				continue;
			}
			$m->$key = $row[ $key ];
		}
		$m->id               = (int) $m->id;
		$m->channel_id       = (int) $m->channel_id;
		$m->period_id        = null !== $m->period_id && '' !== $m->period_id ? (int) $m->period_id : null;
		$m->total_orders     = (int) $m->total_orders;
		$m->total_gross      = (float) $m->total_gross;
		$m->total_deductions = (float) $m->total_deductions;
		$m->total_net        = (float) $m->total_net;
		$m->total_profit     = (float) $m->total_profit;
		if ( isset( $row['channel_name'] ) ) {
			$m->channel_name = (string) $row['channel_name'];
		}
		return $m;
	}

	public function to_array(): array {
		return get_object_vars( $this );
	}

	public function status_label(): string {
		$map = array(
			'pending' => __( 'در انتظار', 'bespari-core' ),
			'paid'    => __( 'پرداخت‌شده', 'bespari-core' ),
		);
		return $map[ $this->status ] ?? $this->status;
	}
}
