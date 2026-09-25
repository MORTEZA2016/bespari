<?php
/**
 * مدل پورسانت.
 *
 * @package Bespari\Modules\Commission
 */

namespace Bespari\Modules\Commission;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommissionModel {

	public int $id               = 0;
	public int $order_id         = 0;
	public int $seller_id        = 0;
	public float $base_amount    = 0.0;
	public string $base_type     = 'net';
	public float $percent        = 0.0;
	public float $amount         = 0.0;
	public string $status        = 'pending';
	public ?string $paid_at      = null;
	public ?int $payout_id       = null;
	public string $created_at    = '';
	public string $updated_at    = '';

	/** Joins */
	public string $order_number  = '';
	public string $seller_name   = '';

	public static function from_row( $row ): self {
		$m   = new self();
		$row = (array) $row;
		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( in_array( $key, array( 'order_number', 'seller_name' ), true ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			if ( null === $row[ $key ] && ! in_array( $key, array( 'paid_at', 'payout_id' ), true ) ) {
				continue;
			}
			$m->$key = $row[ $key ];
		}
		$m->id          = (int) $m->id;
		$m->order_id    = (int) $m->order_id;
		$m->seller_id   = (int) $m->seller_id;
		$m->payout_id   = null !== $m->payout_id && '' !== $m->payout_id ? (int) $m->payout_id : null;
		$m->base_amount = (float) $m->base_amount;
		$m->percent     = (float) $m->percent;
		$m->amount      = (float) $m->amount;
		if ( isset( $row['order_number'] ) ) {
			$m->order_number = (string) $row['order_number'];
		}
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
			'pending'   => __( 'در انتظار', 'bespari-core' ),
			'approved'  => __( 'تاییدشده', 'bespari-core' ),
			'paid'      => __( 'پرداخت‌شده', 'bespari-core' ),
			'cancelled' => __( 'لغوشده', 'bespari-core' ),
		);
		return $map[ $this->status ] ?? $this->status;
	}

	public function base_type_label(): string {
		return 'profit' === $this->base_type ? __( 'سود', 'bespari-core' ) : __( 'خالص', 'bespari-core' );
	}
}
