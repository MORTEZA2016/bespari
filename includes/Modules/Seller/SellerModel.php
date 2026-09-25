<?php
/**
 * مدل بازاریاب.
 *
 * @package Bespari\Modules\Seller
 */

namespace Bespari\Modules\Seller;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SellerModel {

	public int $id               = 0;
	public string $display_name  = '';
	public string $user_login    = '';
	public string $email         = '';
	public float $commission_percent = 0.0;
	public string $seller_status = 'active';
	public string $registered_at = '';

	public static function from_user( \WP_User $user ): self {
		$m = new self();
		$m->id               = (int) $user->ID;
		$m->display_name     = (string) $user->display_name;
		$m->user_login       = (string) $user->user_login;
		$m->email            = (string) $user->user_email;
		$m->registered_at    = (string) $user->user_registered;
		$m->commission_percent = (float) get_user_meta( $user->ID, 'bespari_commission_percent', true );
		$st = get_user_meta( $user->ID, 'bespari_seller_status', true );
		$m->seller_status    = $st ? (string) $st : 'active';
		return $m;
	}

	public function to_array(): array {
		return get_object_vars( $this );
	}

	public function status_label(): string {
		return 'active' === $this->seller_status ? __( 'فعال', 'bespari-core' ) : __( 'غیرفعال', 'bespari-core' );
	}
}
