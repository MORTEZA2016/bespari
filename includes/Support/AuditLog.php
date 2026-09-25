<?php
/**
 * لاگ تغییرات (Audit Log) — ثبت چه کسی، چه زمانی، چه چیزی را تغییر داد.
 *
 * @package Bespari\Support
 */

namespace Bespari\Support;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuditLog {

	/**
	 * ثبت هوک‌ها.
	 */
	public function register(): void {
		// در صورت نیاز به هوک خودکار در آینده.
	}

	/**
	 * ثبت یک رویداد در لاگ.
	 *
	 * @param string $action      نوع عملیات (create|update|delete).
	 * @param string $entity_type نوع موجودیت (channel|brand|product|...).
	 * @param int    $entity_id   آی‌دی موجودیت.
	 * @param mixed  $old_value   مقدار قبلی.
	 * @param mixed  $new_value   مقدار جدید.
	 * @param int    $user_id     آی‌دی کاربر (پیش‌فرض: کاربر فعلی).
	 */
	public static function log(
		string $action,
		string $entity_type,
		int $entity_id,
		$old_value = null,
		$new_value = null,
		int $user_id = 0
	): void {
		global $wpdb;

		if ( ! (int) Helpers::get_setting( 'audit_log_active', 1 ) ) {
			return;
		}

		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		$wpdb->insert(
			Helpers::table( 'audit_log' ),
			array(
				'user_id'     => $user_id,
				'action'      => sanitize_key( $action ),
				'entity_type' => sanitize_key( $entity_type ),
				'entity_id'   => $entity_id,
				'old_value'   => null !== $old_value ? wp_json_encode( $old_value, JSON_UNESCAPED_UNICODE ) : null,
				'new_value'   => null !== $new_value ? wp_json_encode( $new_value, JSON_UNESCAPED_UNICODE ) : null,
				'ip'          => Helpers::client_ip(),
				'created_at'  => Helpers::now(),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		do_action( 'bespari_audit_logged', $action, $entity_type, $entity_id, $user_id );
	}

	/**
	 * ثبت رویداد ایجاد.
	 *
	 * @param string $entity_type نوع موجودیت.
	 * @param int    $entity_id   آی‌دی.
	 * @param mixed  $data        داده‌های ایجادشده.
	 */
	public static function created( string $entity_type, int $entity_id, $data = null ): void {
		self::log( 'create', $entity_type, $entity_id, null, $data );
	}

	/**
	 * ثبت رویداد ویرایش.
	 *
	 * @param string $entity_type نوع موجودیت.
	 * @param int    $entity_id   آی‌دی.
	 * @param mixed  $old         مقدار قبلی.
	 * @param mixed  $new         مقدار جدید.
	 */
	public static function updated( string $entity_type, int $entity_id, $old = null, $new = null ): void {
		self::log( 'update', $entity_type, $entity_id, $old, $new );
	}

	/**
	 * ثبت رویداد حذف.
	 *
	 * @param string $entity_type نوع موجودیت.
	 * @param int    $entity_id   آی‌دی.
	 * @param mixed  $data        داده حذف‌شده.
	 */
	public static function deleted( string $entity_type, int $entity_id, $data = null ): void {
		self::log( 'delete', $entity_type, $entity_id, $data, null );
	}

	/**
	 * دریافت لاگ‌ها با صفحه‌بندی.
	 *
	 * @param int $per_page تعداد در هر صفحه.
	 * @param int $page     شماره صفحه.
	 * @return array{items:array,total:int}
	 */
	public static function fetch( int $per_page = 20, int $page = 1 ): array {
		global $wpdb;

		$table  = Helpers::table( 'audit_log' );
		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore
				$per_page,
				$offset
			)
		);

		return array( 'items' => $items ?: array(), 'total' => $total );
	}
}
