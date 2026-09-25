<?php
/**
 * مدل درخواست گردش کار.
 *
 * @package Bespari\Modules\Request
 */

namespace Bespari\Modules\Request;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RequestModel {

	public int $id            = 0;
	public string $type       = 'other';
	public string $title      = '';
	public string $description = '';
	public int $requester_id   = 0;
	public int $assignee_id    = 0;
	public string $status     = 'pending';
	public int $product_id     = 0;
	public int $order_id       = 0;
	public ?string $resolved_at   = null;
	public string $resolution_notes = '';
	public string $created_at  = '';
	public string $updated_at  = '';

	/** نام‌های جوین‌شده برای نمایش */
	public string $requester_name = '';
	public string $product_name   = '';
	public string $order_number   = '';

	public static function from_row( $row ): self {
		$m   = new self();
		$row = (array) $row;

		foreach ( array_keys( get_class_vars( self::class ) ) as $key ) {
			if ( in_array( $key, array( 'requester_name', 'product_name', 'order_number' ), true ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $row ) ) {
				continue;
			}
			$val = $row[ $key ];
			// فیلدهای nullable می‌توانند null بمانند؛ رشته‌ها را guard می‌کنیم.
			if ( null === $val && ! in_array( $key, array( 'resolved_at', 'description', 'resolution_notes' ), true ) ) {
				continue;
			}
			$m->$key = $val;
		}

		$m->id           = (int) $m->id;
		$m->requester_id = (int) $m->requester_id;
		$m->assignee_id  = (int) $m->assignee_id;
		$m->product_id   = (int) $m->product_id;
		$m->order_id     = (int) $m->order_id;

		// جوین‌ها اگر همراه ردیف آمده باشند.
		if ( isset( $row['requester_name'] ) ) {
			$m->requester_name = (string) $row['requester_name'];
		}
		if ( isset( $row['product_name'] ) ) {
			$m->product_name = (string) $row['product_name'];
		}
		if ( isset( $row['order_number'] ) ) {
			$m->order_number = (string) $row['order_number'];
		}

		return $m;
	}

	public function to_array(): array {
		return get_object_vars( $this );
	}

	public function type_label(): string {
		$map = array(
			'stock'   => __( 'موجودی', 'bespari-core' ),
			'price'   => __( 'قیمت', 'bespari-core' ),
			'discount' => __( 'تخفیف', 'bespari-core' ),
			'other'   => __( 'سایر', 'bespari-core' ),
		);
		return $map[ $this->type ] ?? $this->type;
	}

	public function status_label(): string {
		$map = array(
			'pending'  => __( 'در انتظار', 'bespari-core' ),
			'approved' => __( 'تاییدشده', 'bespari-core' ),
			'rejected' => __( 'ردشده', 'bespari-core' ),
		);
		return $map[ $this->status ] ?? $this->status;
	}
}
