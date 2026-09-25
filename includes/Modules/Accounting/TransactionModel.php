<?php
/**
 * مدل تراکنش کیف پول بازاریاب.
 *
 * @package Bespari\Modules\Accounting
 */

namespace Bespari\Modules\Accounting;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TransactionModel {

	public int $id = 0;
	public int $seller_id = 0;
	public string $type = 'charge';
	public float $amount = 0.0;
	public float $balance_after = 0.0;
	public string $ref_type = '';
	public int $ref_id = 0;
	public string $description = '';
	public int $user_id = 0;
	public string $created_at = '';

	/** نام بازاریاب (join). */
	public string $seller_name = '';

	public static function from_row( array $row ): self {
		$m = new self();
		foreach ( $row as $key => $val ) {
			if ( null === $val && ! in_array( $key, array(), true ) ) {
				continue;
			}
			if ( property_exists( $m, $key ) ) {
				switch ( $key ) {
					case 'id':
					case 'seller_id':
					case 'ref_id':
					case 'user_id':
						$m->$key = (int) $val;
						break;
					case 'amount':
					case 'balance_after':
						$m->$key = (float) $val;
						break;
					default:
						$m->$key = (string) $val;
				}
			}
		}
		return $m;
	}

	public function type_label(): string {
		$labels = array(
			'charge'     => __( 'شارژ', 'bespari-core' ),
			'commission' => __( 'پورسانت', 'bespari-core' ),
			'payout'     => __( 'برداشت/پرداخت', 'bespari-core' ),
			'adjust'     => __( 'تعدیل', 'bespari-core' ),
			'refund'     => __( 'بازگشت وجه', 'bespari-core' ),
		);
		return $labels[ $this->type ] ?? $this->type;
	}
}
