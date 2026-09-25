<?php
/**
 * سرویس اعلان‌های هوشمند بسپاری.
 *
 * @package Bespari\Modules\Notification
 */

namespace Bespari\Modules\Notification;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NotificationService {

	/**
	 * نام جدول.
	 */
	private function table(): string {
		return Helpers::table( 'notifications' );
	}

	/**
	 * ثبت یک اعلان.
	 *
	 * @param array $data [user_id, type, title, body, link].
	 * @return int شناسه اعلان یا ۰.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$title = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
		if ( '' === $title ) {
			return 0;
		}

		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'user_id'    => (int) ( $data['user_id'] ?? 0 ),
				'type'       => sanitize_key( (string) ( $data['type'] ?? 'info' ) ),
				'title'      => $title,
				'body'       => sanitize_textarea_field( (string) ( $data['body'] ?? '' ) ),
				'link'       => esc_url_raw( (string) ( $data['link'] ?? '' ) ),
				'is_read'    => 0,
				'created_at' => Helpers::now(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) ( $inserted ? $wpdb->insert_id : 0 );
	}

	/**
	 * اعلان برای همه کاربران دارای یک cap.
	 *
	 * @param string $cap  قابلیت.
	 * @param array  $data داده اعلان.
	 */
	public function notify_cap( string $cap, array $data ): void {
		$users = get_users( array( 'capability' => $cap, 'fields' => 'ID' ) );

		foreach ( (array) $users as $user_id ) {
			$data['user_id'] = (int) $user_id;
			$this->create( $data );
		}
	}

	/**
	 * لیست اعلان‌های کاربر.
	 *
	 * @param int $user_id  کاربر.
	 * @param int $per_page تعداد.
	 * @return array
	 */
	public function list( int $user_id, int $per_page = 30 ): array {
		global $wpdb;
		$table = $this->table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY is_read ASC, created_at DESC LIMIT %d", // phpcs:ignore
				$user_id,
				$per_page
			)
		);

		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'         => (int) $r->id,
				'type'       => (string) $r->type,
				'title'      => (string) $r->title,
				'body'       => (string) $r->body,
				'link'       => (string) $r->link,
				'is_read'    => (int) $r->is_read,
				'created_at' => (string) $r->created_at,
			);
		}

		return $out;
	}

	/**
	 * تعداد اعلان‌های خوانده‌نشده.
	 *
	 * @param int $user_id کاربر.
	 * @return int
	 */
	public function unread_count( int $user_id ): int {
		global $wpdb;
		$table = $this->table();

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0", $user_id ) // phpcs:ignore
		);
	}

	/**
	 * خواندن یک اعلان.
	 *
	 * @param int $id      شناسه اعلان.
	 * @param int $user_id کاربر (امنیت).
	 * @return bool
	 */
	public function mark_read( int $id, int $user_id ): bool {
		global $wpdb;
		$table = $this->table();

		return (bool) $wpdb->update(
			$table,
			array( 'is_read' => 1 ),
			array( 'id' => $id, 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * خواندن همه اعلان‌های کاربر.
	 *
	 * @param int $user_id کاربر.
	 * @return int تعداد رکوردها.
	 */
	public function mark_all_read( int $user_id ): int {
		global $wpdb;
		$table = $this->table();

		return (int) $wpdb->query(
			$wpdb->prepare( "UPDATE {$table} SET is_read = 1 WHERE user_id = %d AND is_read = 0", $user_id ) // phpcs:ignore
		);
	}

	/**
	 * پاکسازی اعلان‌های قدیمی (بیش از N روز).
	 *
	 * @param int $days تعداد روز.
	 * @return int
	 */
	public function prune( int $days = 60 ): int {
		global $wpdb;
		$table = $this->table();

		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE created_at < DATE_SUB( NOW(), INTERVAL %d DAY )", $days ) // phpcs:ignore
		);
	}
}
