<?php
/**
 * مدل صورتحساب.
 *
 * @package Bespari\Modules\Accounting
 */

namespace Bespari\Modules\Accounting;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InvoiceModel {

	public int $id = 0;
	public string $invoice_number = '';
	public int $order_id = 0;
	public string $type = 'sale';
	public float $total = 0.0;
	public string $meta = '';
	public string $created_at = '';

	/** شماره سفارش (join). */
	public string $order_number = '';

	public static function from_row( array $row ): self {
		$m = new self();
		foreach ( $row as $key => $val ) {
			if ( null === $val && ! in_array( $key, array( 'meta' ), true ) ) {
				continue;
			}
			if ( property_exists( $m, $key ) ) {
				switch ( $key ) {
					case 'id':
					case 'order_id':
						$m->$key = (int) $val;
						break;
					case 'total':
						$m->$key = (float) $val;
						break;
					default:
						$m->$key = (string) $val;
				}
			}
		}
		return $m;
	}

	/**
	 * خطوط صورتحساب از meta (JSON فریز شده).
	 */
	public function lines(): array {
		$decoded = json_decode( (string) $this->meta, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	public function type_label(): string {
		$labels = array(
			'sale'   => __( 'فروش', 'bespari-core' ),
			'refund' => __( 'مرجوعی', 'bespari-core' ),
		);
		return $labels[ $this->type ] ?? $this->type;
	}
}
