<?php
/**
 * کلاس پایه Repository — عملیات مشترک CRUD روی جداول اختصاصی.
 *
 * @package Bespari\Support
 */

namespace Bespari\Support;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseRepository {

	/**
	 * نام جدول بدون پیشوند (مثلاً channels).
	 *
	 * @var string
	 */
	protected string $table = '';

	/**
	 * نام مدل مرتبط.
	 *
	 * @var string
	 */
	protected string $model_class = '';

	/**
	 * نام کلید اصلی.
	 *
	 * @var string
	 */
	protected string $primary_key = 'id';

	/**
	 * نام کامل جدول با پیشوند.
	 *
	 * @return string
	 */
	protected function table(): string {
		return Helpers::table( $this->table );
	}

	/**
	 * دریافت یک رکورد بر اساس آی‌دی.
	 *
	 * @param int $id آی‌دی.
	 * @return object|null
	 */
	public function find( int $id ) {
		global $wpdb;
		$table = $this->table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$this->primary_key} = %d", $id ) // phpcs:ignore
		);

		return $row;
	}

	/**
	 * دریافت همه رکوردها با فیلتر، مرتب‌سازی و صفحه‌بندی.
	 *
	 * @param array  $where    شرایط WHERE (کلید => مقدار).
	 * @param string $orderby  ستون مرتب‌سازی.
	 * @param string $order    ASC|DESC.
	 * @param int    $per_page تعداد.
	 * @param int    $offset   آفست.
	 * @return array
	 */
	public function all( array $where = array(), string $orderby = 'id', string $order = 'DESC', int $per_page = 0, int $offset = 0 ): array {
		global $wpdb;
		$table = $this->table();

		$orderby = preg_replace( '/[^a-zA-Z0-9_]/', '', $orderby );
		$order   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$table}";

		if ( ! empty( $where ) ) {
			$conditions = array();
			$values     = array();
			foreach ( $where as $col => $val ) {
				$col          = preg_replace( '/[^a-zA-Z0-9_]/', '', $col );
				$conditions[] = "{$col} = %s";
				$values[]     = $val;
			}
			$sql .= ' WHERE ' . implode( ' AND ', $conditions );

			if ( $per_page > 0 ) {
				$sql .= " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
				$values[] = $per_page;
				$values[] = $offset;
			} else {
				$sql .= " ORDER BY {$orderby} {$order}";
			}

			// phpcs:disable
			return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
			// phpcs:enable
		}

		if ( $per_page > 0 ) {
			$sql .= " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
			return $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ) ); // phpcs:ignore
		}

		$sql .= " ORDER BY {$orderby} {$order}";
		return $wpdb->get_results( $sql ); // phpcs:ignore
	}

	/**
	 * شمارش رکوردها با فیلتر.
	 *
	 * @param array $where شرایط.
	 * @return int
	 */
	public function count( array $where = array() ): int {
		global $wpdb;
		$table = $this->table();
		$sql   = "SELECT COUNT(*) FROM {$table}";

		if ( ! empty( $where ) ) {
			$conditions = array();
			$values     = array();
			foreach ( $where as $col => $val ) {
				$col          = preg_replace( '/[^a-zA-Z0-9_]/', '', $col );
				$conditions[] = "{$col} = %s";
				$values[]     = $val;
			}
			$sql .= ' WHERE ' . implode( ' AND ', $conditions );

			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore
	}

	/**
	 * درج رکورد جدید.
	 *
	 * @param array $data داده‌ها.
	 * @return int آی‌دی درج‌شده یا 0 در صورت خطا.
	 */
	public function insert( array $data ): int {
		global $wpdb;

		$result = $wpdb->insert( $this->table(), $data );

		return false === $result ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * به‌روزرسانی رکورد.
	 *
	 * @param int   $id   آی‌دی.
	 * @param array $data داده‌ها.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table(),
			$data,
			array( $this->primary_key => $id )
		);

		return false !== $result;
	}

	/**
	 * حذف رکورد.
	 *
	 * @param int $id آی‌دی.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table(),
			array( $this->primary_key => $id )
		);

		return false !== $result;
	}

	/**
	 * تبدیل ردیف‌ها به آرایه‌ای از مدل‌ها.
	 *
	 * @param array $rows ردیف‌ها.
	 * @return array
	 */
	protected function hydrate( array $rows ): array {
		$model_class = $this->model_class;
		$out         = array();

		foreach ( $rows as $row ) {
			$out[] = $model_class::from_row( $row );
		}

		return $out;
	}
}
