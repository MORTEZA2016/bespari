<?php
/**
 * مدیریت دسترسی بازاریاب به برندها و دسته‌بندی‌های محصولات.
 *
 * یک بازاریاب فقط به برندها و دسته‌بندی‌هایی که به او داده شده دسترسی دارد و
 * فقط برای محصولاتِ آن برند+دسته می‌تواند سفارش ثبت کند. کاربرهای دارای
 * cap `bespari_manage_orders` (مدیران/حسابداران) محدود نمی‌شوند.
 *
 * @package Bespari\Modules\Seller
 */

namespace Bespari\Modules\Seller;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SellerAccess {

	/**
	 * آیا این کاربر مشمول محدودیت دسترسی بازاریاب است؟
	 *
	 * @param int $user_id آی‌دی کاربر.
	 * @return bool
	 */
	public static function is_restricted( int $user_id ): bool {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		if ( user_can( $user, 'bespari_manage_orders' ) ) {
			return false;
		}

		return (bool) in_array( 'bespari_seller', (array) $user->roles, true );
	}

	/**
	 * آی‌دی برندهای مجاز برای کاربر.
	 *
	 * @param int $user_id آی‌دی کاربر.
	 * @return int[] خالی یعنی بدون دسترسی.
	 */
	public static function allowed_brand_ids( int $user_id ): array {
		global $wpdb;

		if ( ! $user_id ) {
			return array();
		}

		$table = Helpers::table( 'seller_brands' );

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT brand_id FROM {$table} WHERE user_id = %d", // phpcs:ignore
			$user_id
		) ) ?: array() );
	}

	/**
	 * آی‌دی دسته‌بندی‌های مجاز برای کاربر.
	 *
	 * @param int $user_id آی‌دی کاربر.
	 * @return int[]
	 */
	public static function allowed_category_ids( int $user_id ): array {
		global $wpdb;

		if ( ! $user_id ) {
			return array();
		}

		$table = Helpers::table( 'seller_categories' );

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT category_id FROM {$table} WHERE user_id = %d", // phpcs:ignore
			$user_id
		) ) ?: array() );
	}

	/**
	 * نام برندهای مجاز (برای نمایش).
	 *
	 * @param int $user_id آی‌دی کاربر.
	 * @return array<int,string> brand_id => name.
	 */
	public static function allowed_brands( int $user_id ): array {
		global $wpdb;

		$ids = self::allowed_brand_ids( $user_id );
		if ( empty( $ids ) ) {
			return array();
		}

		$table  = Helpers::table( 'brands' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$query = "SELECT id, name FROM {$table} WHERE id IN ({$placeholders}) ORDER BY name"; // phpcs:ignore

		$out = array();
		$rows = $wpdb->get_results( $wpdb->prepare( $query, $ids ) ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->id ] = (string) $r->name;
		}

		return $out;
	}

	/**
	 * نام دسته‌بندی‌های مجاز (برای نمایش).
	 *
	 * @param int $user_id آی‌دی کاربر.
	 * @return array<int,string> category_id => name.
	 */
	public static function allowed_categories( int $user_id ): array {
		$ids = self::allowed_category_ids( $user_id );
		if ( empty( $ids ) ) {
			return array();
		}

		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'include'    => $ids,
			'hide_empty' => false,
			'orderby'    => 'name',
		) );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $t ) {
			$out[ (int) $t->term_id ] = (string) $t->name;
		}

		return $out;
	}

	/**
	 * ذخیره دسترسی برندها و دسته‌بندی‌های یک کاربر (جایگزین کامل می‌شود).
	 *
	 * @param int   $user_id      آی‌دی کاربر.
	 * @param int[] $brand_ids    آی‌دی برندها.
	 * @param int[] $category_ids آی‌دی دسته‌بندی‌ها.
	 */
	public static function set_access( int $user_id, array $brand_ids, array $category_ids ): void {
		global $wpdb;

		if ( ! $user_id ) {
			return;
		}

		$brands    = Helpers::table( 'seller_brands' );
		$categories = Helpers::table( 'seller_categories' );

		$wpdb->delete( $brands, array( 'user_id' => $user_id ) ); // phpcs:ignore
		$wpdb->delete( $categories, array( 'user_id' => $user_id ) ); // phpcs:ignore

		$now = current_time( 'mysql' );

		$brand_ids = array_filter( array_map( 'intval', $brand_ids ) );
		foreach ( $brand_ids as $bid ) {
			$wpdb->insert( $brands, array( // phpcs:ignore
				'user_id'    => $user_id,
				'brand_id'   => $bid,
				'created_at' => $now,
			) );
		}

		$category_ids = array_filter( array_map( 'intval', $category_ids ) );
		foreach ( $category_ids as $cid ) {
			$wpdb->insert( $categories, array( // phpcs:ignore
				'user_id'      => $user_id,
				'category_id'  => $cid,
				'created_at'   => $now,
			) );
		}
	}

	/**
	 * حذف همه‌ی دسترسی‌های کاربر.
	 *
	 * @param int $user_id آی‌دی کاربر.
	 */
	public static function clear( int $user_id ): void {
		global $wpdb;

		if ( ! $user_id ) {
			return;
		}

		$wpdb->delete( Helpers::table( 'seller_brands' ), array( 'user_id' => $user_id ) ); // phpcs:ignore
		$wpdb->delete( Helpers::table( 'seller_categories' ), array( 'user_id' => $user_id ) ); // phpcs:ignore
	}

	/**
	 * آیا کاربر به این محصول دسترسی دارد؟
	 *
	 * محصول باید هم متعلق به یکی از برندهای مجاز باشد و هم در یکی از
	 * دسته‌بندی‌های مجاز قرار داشته باشد.
	 *
	 * @param int $user_id    آی‌دی کاربر.
	 * @param int $product_id آی‌دی محصول در wp_bespari_products.
	 * @return bool
	 */
	public static function can_access_product( int $user_id, int $product_id ): bool {
		global $wpdb;

		if ( ! self::is_restricted( $user_id ) ) {
			return true;
		}

		if ( ! $product_id ) {
			return false;
		}

		$brands    = self::allowed_brand_ids( $user_id );
		$categories = self::allowed_category_ids( $user_id );

		if ( empty( $brands ) || empty( $categories ) ) {
			return false;
		}

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT brand_id, category_id FROM " . Helpers::table( 'products' ) . " WHERE id = %d", // phpcs:ignore
			$product_id
		) );

		if ( ! $row ) {
			return false;
		}

		return in_array( (int) $row->brand_id, $brands, true )
			&& in_array( (int) $row->category_id, $categories, true );
	}

	/**
	 * کانال‌های در دسترس یک بازاریاب (کانال‌های متصل به برندهای مجاز).
	 *
	 * @param int $user_id آی‌دی کاربر.
	 * @return int[] آی‌دی کانال‌ها.
	 */
	public static function allowed_channel_ids( int $user_id ): array {
		global $wpdb;

		if ( ! self::is_restricted( $user_id ) ) {
			return array();
		}

		$brands = self::allowed_brand_ids( $user_id );
		if ( empty( $brands ) ) {
			return array();
		}

		$table = Helpers::table( 'brand_channels' );
		$placeholders = implode( ',', array_fill( 0, count( $brands ), '%d' ) );

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT channel_id FROM {$table} WHERE brand_id IN ({$placeholders}) AND is_active = 1", // phpcs:ignore
			$brands
		) ) ?: array() );
	}
}
