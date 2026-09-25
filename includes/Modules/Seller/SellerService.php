<?php
/**
 * سرویس بازاریاب‌ها.
 *
 * @package Bespari\Modules\Seller
 */

namespace Bespari\Modules\Seller;

use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SellerService {

	private SellerRepository $repo;

	public function __construct() {
		$this->repo = new SellerRepository();
	}

	public function list( array $args = array() ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters  = array_intersect_key( $args, array_flip( array( 'search', 'status' ) ) );
		return $this->repo->paginate( $filters, $page, $per_page );
	}

	public function get( int $id ): ?SellerModel {
		return $this->repo->find( $id );
	}

	/**
	 * ایجاد بازاریاب جدید.
	 *
	 * @param array $input
	 * @return int|\WP_Error
	 */
	public function create( array $input ) {
		$data = $this->sanitize( $input );

		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			return new \WP_Error( 'invalid_email', __( 'ایمیل معتبر الزامی است.', 'bespari-core' ) );
		}
		if ( email_exists( $data['email'] ) ) {
			return new \WP_Error( 'email_exists', __( 'این ایمیل قبلاً ثبت شده است.', 'bespari-core' ) );
		}
		if ( empty( $data['display_name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'نام نمایشی الزامی است.', 'bespari-core' ) );
		}
		if ( $data['commission_percent'] < 0 || $data['commission_percent'] > 100 ) {
			return new \WP_Error( 'invalid_percent', __( 'درصد پورسانت باید بین ۰ تا ۱۰۰ باشد.', 'bespari-core' ) );
		}

		$user_id = wp_insert_user( array(
			'user_login'   => sanitize_user( $data['user_login'] ?: $data['email'], true ),
			'user_email'   => sanitize_email( $data['email'] ),
			'display_name' => sanitize_text_field( $data['display_name'] ),
			'user_pass'    => $data['password'] ?: wp_generate_password( 12, false ),
			'role'         => 'bespari_seller',
		) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, 'bespari_commission_percent', $data['commission_percent'] );
		update_user_meta( $user_id, 'bespari_seller_status', $data['seller_status'] );

		// درصدهای سفارشی per-channel (فقط کانال‌هایی که مقدار مثبت دارند).
		if ( isset( $input['channel_percents'] ) && is_array( $input['channel_percents'] ) ) {
			$this->repo->set_channel_percents( $user_id, $input['channel_percents'] );
		}

		// اطمینان از نقش حتی اگر wp_insert_user نقش را ست نکرده باشد.
		$user = get_user_by( 'id', $user_id );
		if ( $user && ! in_array( 'bespari_seller', (array) $user->roles, true ) ) {
			$user->add_role( 'bespari_seller' );
		}

		AuditLog::created( 'seller', $user_id, $data );

		return $user_id;
	}

	/**
	 * به‌روزرسانی بازاریاب.
	 *
	 * @param int   $id
	 * @param array $input
	 * @return bool|\WP_Error
	 */
	public function update( int $id, array $input ) {
		$existing = $this->repo->find( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'بازاریاب یافت نشد.', 'bespari-core' ) );
		}

		$data = $this->sanitize( $input );

		if ( isset( $data['commission_percent'] ) && ( $data['commission_percent'] < 0 || $data['commission_percent'] > 100 ) ) {
			return new \WP_Error( 'invalid_percent', __( 'درصد پورسانت باید بین ۰ تا ۱۰۰ باشد.', 'bespari-core' ) );
		}
		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			return new \WP_Error( 'invalid_email', __( 'ایمیل نامعتبر است.', 'bespari-core' ) );
		}
		if ( ! empty( $data['email'] ) && $data['email'] !== $existing->email && email_exists( $data['email'] ) ) {
			return new \WP_Error( 'email_exists', __( 'این ایمیل قبلاً ثبت شده است.', 'bespari-core' ) );
		}

		$old = $existing->to_array();

		$upd = array( 'ID' => $id );
		if ( ! empty( $data['display_name'] ) ) {
			$upd['display_name'] = sanitize_text_field( $data['display_name'] );
		}
		if ( ! empty( $data['email'] ) ) {
			$upd['user_email'] = sanitize_email( $data['email'] );
		}
		if ( ! empty( $data['password'] ) ) {
			$upd['user_pass'] = $data['password'];
		}

		if ( count( $upd ) > 1 ) {
			$res = wp_update_user( $upd );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}

		if ( isset( $data['commission_percent'] ) ) {
			update_user_meta( $id, 'bespari_commission_percent', $data['commission_percent'] );
		}
		if ( isset( $data['seller_status'] ) ) {
			update_user_meta( $id, 'bespari_seller_status', $data['seller_status'] );
		}

		// درصدهای سفارشی per-channel.
		if ( isset( $input['channel_percents'] ) && is_array( $input['channel_percents'] ) ) {
			$this->repo->set_channel_percents( $id, $input['channel_percents'] );
		}

		AuditLog::updated( 'seller', $id, $old, $data );

		return true;
	}

	/**
	 * حذف بازاریاب (حذف نقش، نه کاربر — مگر force).
	 *
	 * @param int  $id
	 * @param bool $delete_user حذف کامل کاربر.
	 * @return bool|\WP_Error
	 */
	public function delete( int $id, bool $delete_user = false ) {
		$existing = $this->repo->find( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'بازاریاب یافت نشد.', 'bespari-core' ) );
		}

		if ( $delete_user ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			$ok = wp_delete_user( $id );
			if ( ! $ok ) {
				return new \WP_Error( 'delete_failed', __( 'خطا در حذف کاربر.', 'bespari-core' ) );
			}
		} else {
			$user = get_user_by( 'id', $id );
			if ( $user ) {
				$user->remove_role( 'bespari_seller' );
			}
		}

		AuditLog::deleted( 'seller', $id, $existing->to_array() );

		return true;
	}

	private function sanitize( array $input ): array {
		$data = array();
		if ( isset( $input['user_login'] ) ) {
			$data['user_login'] = sanitize_user( $input['user_login'], true );
		}
		if ( isset( $input['display_name'] ) ) {
			$data['display_name'] = sanitize_text_field( $input['display_name'] );
		}
		if ( isset( $input['email'] ) ) {
			$data['email'] = sanitize_email( $input['email'] );
		}
		if ( isset( $input['password'] ) ) {
			$data['password'] = (string) $input['password'];
		}
		if ( isset( $input['commission_percent'] ) ) {
			$data['commission_percent'] = Helpers::to_float( $input['commission_percent'] );
		}
		if ( isset( $input['seller_status'] ) ) {
			$st = sanitize_key( $input['seller_status'] );
			$data['seller_status'] = in_array( $st, array( 'active', 'inactive' ), true ) ? $st : 'active';
		}
		return $data;
	}
}
