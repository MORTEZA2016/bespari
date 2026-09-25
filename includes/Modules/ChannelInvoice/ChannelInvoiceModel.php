<?php
/**
 * مدل صورت‌حساب کانالی (بازطراحی تسویه).
 *
 * @package Bespari\Modules\ChannelInvoice
 */

namespace Bespari\Modules\ChannelInvoice;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChannelInvoiceModel {

	public int $id                    = 0;
	public string $invoice_number     = '';
	public int $channel_id            = 0;
	public int $period_days           = 0;
	public string $status             = 'settling';
	public ?string $cash_due_date     = null;
	public ?string $credit_due_date   = null;
	public string $cash_tracking_code = '';
	public ?string $cash_settled_at   = null;
	public string $credit_tracking_code = '';
	public ?string $credit_settled_at = null;
	public float $cash_total          = 0.0;
	public float $credit_total        = 0.0;
	public int $total_batches         = 0;
	public ?array $financials_snapshot = null;
	public string $notes              = '';
	public string $created_at         = '';
	public string $updated_at         = '';
	// فیلدهای JOIN شده.
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

		$model->id           = (int) $model->id;
		$model->channel_id   = (int) $model->channel_id;
		$model->period_days  = (int) $model->period_days;
		$model->cash_total   = (float) $model->cash_total;
		$model->credit_total = (float) $model->credit_total;
		$model->total_batches = (int) $model->total_batches;

		return $model;
	}

	/**
	 * تبدیل به آرایه (بدون snapshot).
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = get_object_vars( $this );
		unset( $data['financials_snapshot'] );
		return $data;
	}

	/**
	 * ترجمه وضعیت صورت‌حساب.
	 *
	 * @return string
	 */
	public function status_label(): string {
		$map = array(
			'settling'        => __( 'در حال تسویه', 'bespari-core' ),
			'partial_settled' => __( 'تسویه گام اول', 'bespari-core' ),
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
			'settling'        => 'bespari-badge--muted',
			'partial_settled' => 'bespari-badge--warning',
			'settled'         => 'bespari-badge--success',
		);

		return $map[ $this->status ] ?? 'bespari-badge--muted';
	}
}
