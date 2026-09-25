<?php
/**
 * سرویس مدیریت کاربران افزونه — ایجاد/ویرایش/حذف کاربران دارای نقش bespari_*.
 *
 * @package Bespari\Modules\User
 */

namespace Bespari\Modules\User;

use Bespari\Api\AppAuth;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserService {

	/**
	 * نقش‌های قابل تخصیص توسط افزونه.
	 *
	 * @return array key => label.
	 */
	public static function roles(): array {
		return array(
			'bespari_admin'       => __( 'مدیر بسپاری ERP', 'bespari-core' ),
			'bespari_accountant'  => __( 'حسابدار بسپاری', 'bespari-core' ),
			'bespari_warehouse'   => __( 'انباردار بسپاری', 'bespari-core' ),
			'bespari_seller'      => __( 'بازاریاب بسپاری', 'bespari-core' ),
			'bespari_production'  => __( 'مدیر تولید بسپاری', 'bespari-core' ),
			'bespari_viewer'      => __( 'بیننده بسپاری', 'bespari-core' ),
		);
	}

	/**
	 * لیست کاربران دارای حداقل یک نقش bespari_*.
	 *
	 * @param array $args s، role، paged، per_page.
	 * @return array ['rows' => [...], 'pages' => int, 'page' => int]
	 */
	public function list( array $args = array() ): array {
		$s        = sanitize_text_field( $args['s'] ?? '' );
		$role     = isset( $args['role'] ) ? sanitize_key( $args['role'] ) : '';
		$page     = max( 1, (int) ( $args['paged'] ?? 1 ) );
		$per_page = 20;

		$query_args = array(
			'number'  => $per_page,
			'paged'   => $page,
			'orderby' => 'ID',
			'order'   => 'DESC',
			'fields'  => array( 'ID', 'user_login', 'display_name', 'user_email' ),
		);

		if ( '' !== $s ) {
			$query_args['search'] = '*' . $s . '*';
		}

		if ( '' !== $role && isset( self::roles()[ $role ] ) ) {
			$query_args['role__in'] = array( $role );
		} else {
			$query_args['role__in'] = array_keys( self::roles() );
		}

		$q = new \WP_User_Query( $query_args );
		$total = (int) $q->get_total();

		$rows = array();
		foreach ( $q->get_results() as $u ) {
			$user  = get_user_by( 'id', $u->ID );
			$roles = array_values( array_intersect( (array) $user->roles, array_keys( self::roles() ) ) );
			$label = isset( self::roles()[ $roles[0] ?? '' ] ) ? self::roles()[ $roles[0] ] : '—';

			$rows[] = array(
				'id'       => $u->ID,
				'login'    => $u->user_login,
				'name'     => $u->display_name,
				'email'    => $u->user_email,
				'role'     => $roles[0] ?? '',
				'role_label' => $label,
				'tokens'   => count( (array) get_user_meta( $u->ID, AppAuth::META_KEY, true ) ),
			);
		}

		return array(
			'rows'  => $rows,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
			'page'  => $page,
		);
	}

	/**
	 * ایجاد کاربر با نقش bespari.
	 *
	 * @param array $input ورودی فرم.
	 * @return int|WP_Error
	 */
	public function create( array $input ) {
		$data = $this->sanitize( $input );

		if ( empty( $data['user_login'] ) ) {
			return new \WP_Error( 'missing_login', __( 'نام کاربری الزامی است.', 'bespari-core' ) );
		}
		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			return new \WP_Error( 'invalid_email', __( 'ایمیل معتبر الزامی است.', 'bespari-core' ) );
		}
		if ( email_exists( $data['email'] ) ) {
			return new \WP_Error( 'email_exists', __( 'این ایمیل قبلاً ثبت شده است.', 'bespari-core' ) );
		}
		if ( username_exists( $data['user_login'] ) ) {
			return new \WP_Error( 'login_exists', __( 'این نام کاربری قبلاً ثبت شده است.', 'bespari-core' ) );
		}
		if ( empty( $data['display_name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'نام نمایشی الزامی است.', 'bespari-core' ) );
		}
		if ( ! isset( self::roles()[ $data['role'] ] ) ) {
			return new \WP_Error( 'invalid_role', __( 'نقش انتخاب‌شده معتبر نیست.', 'bespari-core' ) );
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $data['user_login'],
			'user_email'   => $data['email'],
			'display_name' => $data['display_name'],
			'user_pass'    => $data['password'] ?: wp_generate_password( 12, false ),
			'role'         => $data['role'],
		) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// اطمینان از نقش حتی اگر wp_insert_user آن را ست نکرده باشد.
		$user = get_user_by( 'id', $user_id );
		if ( $user && ! in_array( $data['role'], (array) $user->roles, true ) ) {
			$user->add_role( $data['role'] );
		}

		AuditLog::created( 'user', $user_id, $data );

		return $user_id;
	}

	/**
	 * به‌روزرسانی کاربر.
	 *
	 * @param int   $id    آی‌دی کاربر.
	 * @param array $input ورودی فرم.
	 * @return bool|WP_Error
	 */
	public function update( int $id, array $input ) {
		$user = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new \WP_Error( 'not_found', __( 'کاربر یافت نشد.', 'bespari-core' ) );
		}

		$data = $this->sanitize( $input );

		if ( empty( $data['display_name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'نام نمایشی الزامی است.', 'bespari-core' ) );
		}
		if ( ! isset( self::roles()[ $data['role'] ] ) ) {
			return new \WP_Error( 'invalid_role', __( 'نقش انتخاب‌شده معتبر نیست.', 'bespari-core' ) );
		}
		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			return new \WP_Error( 'invalid_email', __( 'ایمیل معتبر نیست.', 'bespari-core' ) );
		}
		if ( ! empty( $data['email'] ) && email_exists( $data['email'] ) && email_exists( $data['email'] ) !== $id ) {
			return new \WP_Error( 'email_exists', __( 'این ایمیل متعلق به کاربر دیگری است.', 'bespari-core' ) );
		}

		$update = array( 'ID' => $id, 'display_name' => $data['display_name'] );

		if ( ! empty( $data['email'] ) ) {
			$update['user_email'] = $data['email'];
		}
		if ( ! empty( $data['password'] ) ) {
			$update['user_pass'] = $data['password'];
		}

		$result = wp_update_user( $update );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// جابه‌جایی نقش bespari.
		$current = array_values( array_intersect( (array) $user->roles, array_keys( self::roles() ) ) );
		$current = $current[0] ?? '';

		if ( $current !== $data['role'] ) {
			if ( '' !== $current ) {
				$user->remove_role( $current );
			}
			$user->add_role( $data['role'] );
		}

		if ( ! empty( $data['password'] ) ) {
			// تغییر رمز → ابطال توکن‌های قدیمی.
			delete_user_meta( $id, AppAuth::META_KEY );
		}

		AuditLog::updated( 'user', $id, array( 'role' => $current ), array( 'role' => $data['role'] ) );

		return true;
	}

	/**
	 * حذف کاربر (با محافظت از ادمین جاری).
	 *
	 * @param int $id آی‌دی کاربر.
	 * @return bool|WP_Error
	 */
	public function delete( int $id ) {
		if ( get_current_user_id() === $id ) {
			return new \WP_Error( 'cannot_delete_self', __( 'نمی‌توانید حساب کاربری خود را حذف کنید.', 'bespari-core' ) );
		}

		$user = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new \WP_Error( 'not_found', __( 'کاربر یافت نشد.', 'bespari-core' ) );
		}

		$has_bespari = (bool) array_intersect( (array) $user->roles, array_keys( self::roles() ) );

		if ( ! $has_bespari ) {
			return new \WP_Error( 'not_bespari_user', __( 'این کاربر متعلق به بسپاری نیست.', 'bespari-core' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$ok = wp_delete_user( $id );

		if ( $ok ) {
			AuditLog::deleted( 'user', $id, array( 'login' => $user->user_login ) );
		}

		return $ok ? true : new \WP_Error( 'delete_failed', __( 'خطا در حذف کاربر.', 'bespari-core' ) );
	}

	/**
	 * صدور توکن جدید برای کاربر (یک‌بار نمایش داده می‌شود).
	 *
	 * @param int $id آی‌دی کاربر.
	 * @return string|WP_Error توکن خام یا خطا.
	 */
	public function issue_token( int $id ) {
		$user = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new \WP_Error( 'not_found', __( 'کاربر یافت نشد.', 'bespari-core' ) );
		}

		$has_bespari = (bool) array_intersect( (array) $user->roles, array_keys( self::roles() ) );

		if ( ! $has_bespari ) {
			return new \WP_Error( 'not_bespari_user', __( 'این کاربر متعلق به بسپاری نیست.', 'bespari-core' ) );
		}

		return AppAuth::issue( $user );
	}

	/**
	 * ابطال همه‌ی توکن‌های کاربر.
	 *
	 * @param int $id آی‌دی کاربر.
	 * @return bool|WP_Error
	 */
	public function revoke_tokens( int $id ) {
		$user = get_user_by( 'id', $id );

		if ( ! $user ) {
			return new \WP_Error( 'not_found', __( 'کاربر یافت نشد.', 'bespari-core' ) );
		}

		delete_user_meta( $id, AppAuth::META_KEY );

		AuditLog::updated( 'user', $id, array(), array( 'tokens_revoked' => true ) );

		return true;
	}

	/**
	 * پاک‌سازی ورودی.
	 *
	 * @param array $input ورودی خام.
	 * @return array
	 */
	private function sanitize( array $input ): array {
		return array(
			'user_login'   => sanitize_user( $input['user_login'] ?? '', true ),
			'email'        => sanitize_email( $input['email'] ?? '' ),
			'display_name' => sanitize_text_field( $input['display_name'] ?? '' ),
			'role'         => sanitize_key( $input['role'] ?? '' ),
			'password'     => (string) ( $input['password'] ?? '' ),
		);
	}
}
