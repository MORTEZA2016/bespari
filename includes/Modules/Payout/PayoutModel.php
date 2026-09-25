<?php
/**
 * مدل پرداخت/برداشت.
 *
 * @package Bespari\Modules\Payout
 */

namespace Bespari\Modules\Payout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayoutModel {

	public int $id              = 0;
	public int $seller_id       = 0;
	public float $amount        = 0.0;
	public string $method       = 'manual';
	public string $status       = 'pending';
	public string $requested_at = '';
	public ?string $paid_at     = null;
	public string $notes        = '';
	public string $created_at   = '';
	public string $updated_at   = '';

	public string $seller_name  = '';

	public static function from_row( $row ): self {
		$m   = new self();
		$row = (array) $row;
		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( 'seller_name' === $key ) {
				continue;
			}
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			if ( null === $row[ $key ] && 'paid_at' !== $key ) {
				continue;
			}
			$m->$key = $row[ $key ];
		}
		$m->id        = (int) $m->id;
		$m->seller_id = (int) $m->seller_id;
		$m->amount    = (float) $m->amount;
		if ( isset( $row['seller_name'] ) ) {
			$m->seller_name = (string) $row['seller_name'];
		}
		return $m;
	}

	public function to_array(): array {
		return get_object_vars( $this );
	}

	public function status_label(): string {
		$map = array(
			'pending'  => __( 'در انتظار', 'bespari-core' ),
			'approved' => __( 'تاییدشده', 'bespari-core' ),
			'paid'     => __( 'پرداخت‌شده', 'bespari-core' ),
			'rejected' => __( 'ردشده', 'bespari-core' ),
		);
		return $map[ $this->status ] ?? $this->status;
	}
}
