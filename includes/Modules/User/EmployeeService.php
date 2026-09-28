<?php
/**
 * سرویس مدیریت کارمندان افزونه — کاربران داخلی (به‌جز بازاریاب‌ها).
 *
 * کارمند: نام، نام خانوادگی، موبایل، عکس، نقش و دسترسی‌های دقیق.
 * عکس به‌صورت attachment وردپرس ذخیره می‌شود (آپلود base64 از پنل).
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

class EmployeeService {

	public const MOBILE_META = 'bespari_mobile';
	public const PHOTO_META  = 'bespari_photo';

	/**
	 * بیشینه‌ی حجم عکس (بایت).
	 */
	private const PHOTO_MAX_BYTES = 2097152; // ۲MB.

	/**
	 * نقش‌های قابل تخصیص به کارمند (بازاریاب‌ها ماژول مستقل دارند).
	 *
	 * @return array key => label
	 */
	public static function roles(): array {
		$roles = UserService::roles();
		unset( $roles['bespari_seller'] );

		return $roles;
	}

	/**
	 * آیا کاربر یک کارمند داخلی است؟
	 *
	 * @param \WP_User $user کاربر.
	 */
	public function is_employee( \WP_User $user ): bool {
		return (bool) array_intersect( (array) $user->roles, array_keys( self::roles() ) );
	}

	/**
	 * لیست کارمندان.
	 *
	 * @param array $args s, role, page.
	 * @return array {rows, pages, page}
	 */
	public function list( array $args = array() ): array {
		$s        = trim( (string) ( $args['s'] ?? '' ) );
		$role     = isset( $args['role'] ) ? sanitize_key( $args['role'] ) : '';
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = 20;

		$query_args = array(
			'number'  => $per_page,
			'paged'   => $page,
			'orderby' => 'ID',
			'order'   => 'DESC',
			'fields'  => array( 'ID' ),
		);

		if ( '' !== $s ) {
			$query_args['search']         = '*' . $s . '*';
			$query_args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
		}

		if ( '' !== $role && isset( self::roles()[ $role ] ) ) {
			$query_args['role__in'] = array( $role );
		} else {
			$query_args['role__in'] = array_keys( self::roles() );
		}

		$q     = new \WP_User_Query( $query_args );
		$total = (int) $q->get_total();

		$rows = array();
		foreach ( $q->get_results() as $u ) {
			$user  = get_user_by( 'id', $u->ID );
			$roles = array_values( array_intersect( (array) $user->roles, array_keys( self::roles() ) ) );

			$rows[] = array(
				'id'           => (int) $user->ID,
				'login'        => $user->user_login,
				'first_name'   => get_user_meta( $user->ID, 'first_name', true ),
				'last_name'    => get_user_meta( $user->ID, 'last_name', true ),
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'mobile'       => get_user_meta( $user->ID, self::MOBILE_META, true ),
				'photo'        => $this->photo_url( $user ),
				'role'         => $roles[0] ?? '',
				'role_label'   => self::roles()[ $roles[0] ?? '' ] ?? '—',
				'permissions'  => PermissionService::grants_summary( (int) $user->ID ),
			);
		}

		return array(
			'rows'  => $rows,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
			'page'  => $page,
		);
	}

	/**
	 * داده‌ی کامل یک کارمند (برای prefill فرم ویرایش).
	 *
	 * @param int $id شناسه کاربر.
	 * @return array|null
	 */
	public function get_form_values( int $id ): ?array {
		$user = get_user_by( 'id', $id );

		if ( ! $user || ! $this->is_employee( $user ) ) {
			return null;
		}

		$roles = array_values( array_intersect( (array) $user->roles, array_keys( self::roles() ) ) );
		$grants = PermissionService::get_grants( $id );

		return array(
			'user_login'  => $user->user_login,
			'first_name'  => get_user_meta( $id, 'first_name', true ),
			'last_name'   => get_user_meta( $id, 'last_name', true ),
			'email'       => $user->user_email,
			'mobile'      => get_user_meta( $id, self::MOBILE_META, true ),
			'photo'       => $this->photo_url( $user ),
			'role'        => $roles[0] ?? '',
			'permissions' => $grants['menus'],
			'channel_view'=> $grants['channel_view'],
			'allowed_channels'   => $grants['entities']['channels'] ?? array(),
			'allowed_brands'     => $grants['entities']['brands'] ?? array(),
			'allowed_categories' => $grants['entities']['categories'] ?? array(),
			'allowed_products'   => $grants['entities']['products'] ?? array(),
		);
	}

	/**
	 * ایجاد کارمند.
	 *
	 * @param array $input ورودی فرم.
	 * @return int|\WP_Error
	 */
	public function create( array $input ) {
		$data = $this->sanitize( $input );

		$errors = $this->validate( $data, 0 );
		if ( is_wp_error( $errors ) ) {
			return $errors;
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $data['user_login'],
			'user_email'   => $data['email'],
			'display_name' => trim( $data['first_name'] . ' ' . $data['last_name'] ),
			'first_name'   => $data['first_name'],
			'last_name'    => $data['last_name'],
			'user_pass'    => $data['password'] ?: wp_generate_password( 12, false ),
			'role'         => $data['role'],
		) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user = get_user_by( 'id', $user_id );
		if ( $user && ! in_array( $data['role'], (array) $user->roles, true ) ) {
			$user->add_role( $data['role'] );
		}

		update_user_meta( $user_id, self::MOBILE_META, $data['mobile'] );

		if ( ! empty( $input['photo_data'] ) ) {
			$this->set_photo( $user_id, (string) $input['photo_data'] );
		}

		if ( isset( $input['permissions'] ) || isset( $input['channel_view'] ) || isset( $input['allowed_channels'] ) ) {
			$this->save_permissions( $user_id, $input );
		}

		AuditLog::created( 'employee', $user_id, $data );

		return $user_id;
	}

	/**
	 * به‌روزرسانی کارمند.
	 *
	 * @param int   $id    شناسه کاربر.
	 * @param array $input ورودی فرم.
	 * @return bool|\WP_Error
	 */
	public function update( int $id, array $input ) {
		$user = get_user_by( 'id', $id );

		if ( ! $user || ! $this->is_employee( $user ) ) {
			return new \WP_Error( 'not_found', __( 'کارمند یافت نشد.', 'bespari-core' ) );
		}

		$data   = $this->sanitize( $input );
		$errors = $this->validate( $data, $id );

		if ( is_wp_error( $errors ) ) {
			return $errors;
		}

		$update = array(
			'ID'         => $id,
			'display_name' => trim( $data['first_name'] . ' ' . $data['last_name'] ),
			'first_name' => $data['first_name'],
			'last_name'  => $data['last_name'],
		);

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

		// جابه‌جایی نقش کارمندی.
		$current = array_values( array_intersect( (array) $user->roles, array_keys( self::roles() ) ) );
		$current = $current[0] ?? '';

		if ( $current !== $data['role'] ) {
			if ( '' !== $current ) {
				$user->remove_role( $current );
			}
			if ( '' !== $data['role'] ) {
				$user->add_role( $data['role'] );
			}
		}

		update_user_meta( $id, self::MOBILE_META, $data['mobile'] );

		if ( ! empty( $input['photo_data'] ) ) {
			$this->set_photo( $id, (string) $input['photo_data'] );
		}

		$this->save_permissions( $id, $input );

		if ( ! empty( $data['password'] ) ) {
			delete_user_meta( $id, AppAuth::META_KEY );
		}

		AuditLog::updated( 'employee', $id, array( 'role' => $current ), array( 'role' => $data['role'] ) );

		return true;
	}

	/**
	 * حذف کارمند.
	 *
	 * @param int $id شناسه کاربر.
	 * @return bool|\WP_Error
	 */
	public function delete( int $id ) {
		if ( get_current_user_id() === $id ) {
			return new \WP_Error( 'cannot_delete_self', __( 'نمی‌توانید حساب کاربری خود را حذف کنید.', 'bespari-core' ) );
		}

		$user = get_user_by( 'id', $id );

		if ( ! $user || ! $this->is_employee( $user ) ) {
			return new \WP_Error( 'not_found', __( 'کارمند یافت نشد.', 'bespari-core' ) );
		}

		$this->delete_photo( $id );
		PermissionService::clear_grants( $id );

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$ok = wp_delete_user( $id );

		if ( ! $ok ) {
			return new \WP_Error( 'delete_failed', __( 'خطا در حذف کارمند.', 'bespari-core' ) );
		}

		AuditLog::deleted( 'employee', $id, array( 'login' => $user->user_login ) );

		return true;
	}

	/**
	 * ذخیره‌سازی دسترسی‌های کارمند.
	 *
	 * @param int   $user_id شناسه کاربر.
	 * @param array $input   ورودی فرم.
	 */
	private function save_permissions( int $user_id, array $input ): void {
		$raw = array(
			'menus'        => $input['permissions'] ?? array(),
			'channel_view' => isset( $input['channel_view'] ) ? (bool) $input['channel_view'] : true,
			'entities'     => array(
				'channels'   => $input['allowed_channels'] ?? array(),
				'brands'     => $input['allowed_brands'] ?? array(),
				'categories' => $input['allowed_categories'] ?? array(),
				'products'   => $input['allowed_products'] ?? array(),
			),
		);

		PermissionService::set_grants( $user_id, $raw );
	}

	/**
	 * URL عکس کارمند (attachment، URL یا avatar پیش‌فرض).
	 *
	 * @param \WP_User $user کاربر.
	 * @return string
	 */
	public function photo_url( \WP_User $user ): string {
		$photo = get_user_meta( $user->ID, self::PHOTO_META, true );

		if ( is_numeric( $photo ) && (int) $photo > 0 ) {
			$url = wp_get_attachment_image_url( (int) $photo, 'thumbnail' );
			if ( $url ) {
				return $url;
			}
		}

		if ( is_string( $photo ) && '' !== $photo && false !== strpos( $photo, 'http' ) ) {
			return esc_url_raw( $photo );
		}

		$avatar = get_avatar_url( $user->ID, array( 'size' => 96, 'default' => 'identicon' ) );

		return is_string( $avatar ) ? $avatar : '';
	}

	/**
	 * آپلود عکس کارمند از داده‌ی base64 (data URI یا base64 خالص).
	 *
	 * @param int    $user_id شناسه کاربر.
	 * @param string $data    داده‌ی عکس.
	 * @return array {id, url} یا WP_Error.
	 */
	public function set_photo( int $user_id, string $data ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user || ! $this->is_employee( $user ) ) {
			return new \WP_Error( 'not_found', __( 'کارمند یافت نشد.', 'bespari-core' ) );
		}

		$mime  = '';
		$base64 = $data;

		if ( 0 === strpos( $data, 'data:' ) ) {
			// data:image/png;base64,xxxx
			if ( ! preg_match( '#^data:([a-z0-9/.+-]+);base64,(.+)$#is', $data, $m ) ) {
				return new \WP_Error( 'bad_photo', __( 'فرمت عکس ارسالی نامعتبر است.', 'bespari-core' ) );
			}
			$mime   = strtolower( $m[1] );
			$base64 = $m[2];
		}

		$bits = base64_decode( $base64, true );

		if ( false === $bits || '' === $bits ) {
			return new \WP_Error( 'bad_photo', __( 'عکس ارسالی قابل خواندن نیست.', 'bespari-core' ) );
		}

		if ( strlen( $bits ) > self::PHOTO_MAX_BYTES ) {
			return new \WP_Error( 'photo_too_big', __( 'حجم عکس باید کمتر از ۲ مگابایت باشد.', 'bespari-core' ) );
		}

		$allowed = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		);

		if ( '' === $mime ) {
			// تشخیص از روی امضای فایل.
			$mime = $this->sniff_mime( $bits );
		}

		if ( ! isset( $allowed[ $mime ] ) ) {
			return new \WP_Error( 'photo_type', __( 'فقط عکس‌های JPG، PNG، WEBP و GIF مجاز هستند.', 'bespari-core' ) );
		}

		// حذف عکس قبلی پیش از ساختن پیوست جدید — تا هیچ‌وقت عکس جدید حذف نشود.
		$this->delete_photo( $user_id );

		$filename = 'bespari-employee-' . $user_id . '-' . wp_rand( 1000, 9999 ) . '.' . $allowed[ $mime ];
		$upload   = wp_upload_bits( $filename, null, $bits );

		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'upload_failed', $upload['error'] );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attach_id = wp_insert_attachment( array(
			'post_title'     => $filename,
			'post_mime_type' => $mime,
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => get_current_user_id(),
		), $upload['file'], 0 );

		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			return new \WP_Error( 'upload_failed', __( 'ذخیره‌ی عکس ناموفق بود.', 'bespari-core' ) );
		}

		$meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		if ( $meta ) {
			wp_update_attachment_metadata( $attach_id, $meta );
		}

		update_user_meta( $user_id, self::PHOTO_META, $attach_id );

		return array(
			'id'  => (int) $attach_id,
			'url' => (string) wp_get_attachment_image_url( $attach_id, 'thumbnail' ),
		);
	}

	/**
	 * حذف عکس کارمند (همراه با attachment).
	 *
	 * @param int $user_id شناسه کاربر.
	 */
	public function delete_photo( int $user_id ): void {
		$photo = (int) get_user_meta( $user_id, self::PHOTO_META, true );

		if ( $photo > 0 && get_attached_file( $photo ) ) {
			wp_delete_attachment( $photo, true );
		}

		delete_user_meta( $user_id, self::PHOTO_META );
	}

	/**
	 * تشخیص نوع عکس از روی امضای بایت‌ها.
	 *
	 * @param string $bits داده‌ی باینری.
	 */
	private function sniff_mime( string $bits ): string {
		if ( strlen( $bits ) < 12 ) {
			return '';
		}

		$head = substr( $bits, 0, 12 );

		if ( "\xFF\xD8\xFF" === substr( $head, 0, 3 ) ) {
			return 'image/jpeg';
		}
		if ( "\x89PNG\r\n\x1A\n" === substr( $head, 0, 8 ) ) {
			return 'image/png';
		}
		if ( 'GIF87a' === substr( $head, 0, 6 ) || 'GIF89a' === substr( $head, 0, 6 ) ) {
			return 'image/gif';
		}
		if ( 'RIFF' === substr( $head, 0, 4 ) && 'WEBP' === substr( $head, 8, 4 ) ) {
			return 'image/webp';
		}

		return '';
	}

	/**
	 * اعتبارسنجی داده‌های کارمند.
	 *
	 * @param array $data داده‌ی پاک‌شده.
	 * @param int   $id   شناسه (۰ برای ایجاد).
	 */
	private function validate( array $data, int $id ) {
		if ( 0 === $id ) {
			if ( '' === $data['user_login'] ) {
				return new \WP_Error( 'missing_login', __( 'نام کاربری الزامی است.', 'bespari-core' ) );
			}
			if ( username_exists( $data['user_login'] ) ) {
				return new \WP_Error( 'login_exists', __( 'این نام کاربری قبلاً ثبت شده است.', 'bespari-core' ) );
			}
		}

		if ( '' === $data['first_name'] && '' === $data['last_name'] ) {
			return new \WP_Error( 'missing_name', __( 'حداقل نام یا نام خانوادگی الزامی است.', 'bespari-core' ) );
		}

		if ( '' === $data['email'] || ! is_email( $data['email'] ) ) {
			return new \WP_Error( 'invalid_email', __( 'ایمیل معتبر الزامی است.', 'bespari-core' ) );
		}

		$owner = email_exists( $data['email'] );
		if ( $owner && (int) $owner !== $id ) {
			return new \WP_Error( 'email_exists', __( 'این ایمیل متعلق به کاربر دیگری است.', 'bespari-core' ) );
		}

		if ( ! isset( self::roles()[ $data['role'] ] ) ) {
			return new \WP_Error( 'invalid_role', __( 'نقش انتخاب‌شده معتبر نیست.', 'bespari-core' ) );
		}

		return null;
	}

	/**
	 * پاک‌سازی ورودی.
	 *
	 * @param array $input ورودی خام.
	 */
	private function sanitize( array $input ): array {
		return array(
			'user_login' => sanitize_user( (string) ( $input['user_login'] ?? '' ), true ),
			'first_name' => sanitize_text_field( (string) ( $input['first_name'] ?? '' ) ),
			'last_name'  => sanitize_text_field( (string) ( $input['last_name'] ?? '' ) ),
			'email'      => sanitize_email( (string) ( $input['email'] ?? '' ) ),
			'mobile'     => $this->sanitize_mobile( (string) ( $input['mobile'] ?? '' ) ),
			'role'       => sanitize_key( (string) ( $input['role'] ?? '' ) ),
			'password'   => (string) ( $input['password'] ?? '' ),
		);
	}

	/**
	 * پاک‌سازی موبایل (فقط ارقام، + و فاصله).
	 *
	 * @param string $mobile موبایل خام.
	 */
	private function sanitize_mobile( string $mobile ): string {
		$mobile = preg_replace( '/[^\d+]/', '', $mobile );

		return is_string( $mobile ) ? substr( $mobile, 0, 20 ) : '';
	}
}
