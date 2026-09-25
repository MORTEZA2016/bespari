<?php
/**
 * مدل محموله کانالی (بازطراحی تسویه).
 *
 * @package Bespari\Modules\Batch
 */

namespace Bespari\Modules\Batch;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BatchModel {

	public int $id                    = 0;
	public string $batch_code         = '';
	public int $channel_id            = 0;
	public string $settlement_mode    = 'batch';
	public string $status             = 'pending';
	public string $cash_tracking_code = '';
	public ?string $cash_settled_at   = null;
	public string $credit_tracking_code = '';
	public ?string $credit_settled_at = null;
	public float $cash_total          = 0.0;
	public float $credit_total        = 0.0;
	public int $total_orders          = 0;
	public ?array $financials_snapshot = null;
	public ?string $shipped_at        = null;
	public int $channel_invoice_id    = 0;
	public string $notes              = '';
	public string $created_at         = '';
	public string $updated_at         = '';
	// فیلدهای JOIN شده (نمایشی).
	public string $channel_name       = '';

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
			if ( 'financials_snapshot' === $key && is_string( $val ) && '' !== $val ) {
				$decoded = json_decode( $val, true );
				$model->$key = is_array( $decoded ) ? $decoded : null;
			} else {
				$model->$key = $val;
			}
		}

		$model->id     = (int) $model->id;
		$model->channel_id = (int) $model->channel_id;
		$model->cash_total = (float) $model->cash_total;
		$model->credit_total = (float) $model->credit_total;
		$model->total_orders = (int) $model->total_orders;
		$model->channel_invoice_id = (int) $model->channel_invoice_id;

		return $model;
	}

	/**
	 * تبدیل به آرایه (بدون snapshot برای لاگ سبک).
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = get_object_vars( $this );
		unset( $data['financials_snapshot'] );
		return $data;
	}

	/**
	 * ترجمه وضعیت محموله.
	 *
	 * @return string
	 */
	public function status_label(): string {
		$map = array(
			'pending'         => __( 'در انتظار ارسال', 'bespari-core' ),
			'shipped'         => __( 'ارسال شد', 'bespari-core' ),
			'partial_settled' => __( 'تسویه بخش اول', 'bespari-core' ),
			'settled'         => __( 'تسویه کامل', 'bespari-core' ),
		);

		return $map[ $this->status ] ?? $this->status;
	}

	/**
	 * کلاس badge وضعیت.
	 *
	 * @return string
	 */
	public function status_badge(): string {
		$map = array(
			'pending'         => 'bespari-badge--muted',
			'shipped'         => 'bespari-badge--info',
			'partial_settled' => 'bespari-badge--warning',
			'settled'         => 'bespari-badge--success',
		);

		return $map[ $this->status ] ?? 'bespari-badge--muted';
	}
}
