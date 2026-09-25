<?php
/**
 * Repository بازاریاب‌ها — روی WP_User_Query.
 *
 * @package Bespari\Modules\Seller
 */

namespace Bespari\Modules\Seller;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SellerRepository {

	/**
	 * لیست صفحه‌بندی‌شده بازاریاب‌ها.
	 *
	 * @param array $filters search, status
	 * @param int   $page
	 * @param int   $per_page
	 * @return array{items:SellerModel[],total:int}
	 */
	public function paginate( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		$args = array(
			'role'   => 'bespari_seller',
			'number' => $per_page,
			'paged'  => $page,
		);

		if ( ! empty( $filters['search'] ) ) {
			$args['search']         = '*' . esc_attr( $filters['search'] ) . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		if ( ! empty( $filters['status'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => 'bespari_seller_status',
					'value' => sanitize_key( $filters['status'] ),
				),
			);
		}

		$query = new \WP_User_Query( $args );
		$items = array();
		foreach ( (array) $query->get_results() as $user ) {
			$items[] = SellerModel::from_user( $user );
		}

		return array(
			'items' => $items,
			'total' => (int) $query->get_total(),
		);
	}

	public function find( int $user_id ): ?SellerModel {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! in_array( 'bespari_seller', (array) $user->roles, true ) ) {
			return null;
		}
		return SellerModel::from_user( $user );
	}

	public function count(): int {
		$q = new \WP_User_Query( array( 'role' => 'bespari_seller', 'number' => 1, 'fields' => 'ID' ) );
		return (int) $q->get_total();
	}

	public function get_commission_percent( int $seller_id ): float {
		return (float) get_user_meta( $seller_id, 'bespari_commission_percent', true );
	}

	public function set_commission_percent( int $seller_id, float $percent ): void {
		update_user_meta( $seller_id, 'bespari_commission_percent', $percent );
	}

	/**
	 * درصد پورسانت سفارشی بازاریاب برای یک کانال (null = درصد سفارشی ندارد، ملاک درصد کانال است).
	 */
	public function get_channel_percent( int $seller_id, int $channel_id ): ?float {
		$map = get_user_meta( $seller_id, 'bespari_commission_channels', true );
		$map = is_array( $map ) ? $map : array();

		$key = (string) $channel_id;
		if ( ! isset( $map[ $key ] ) ) {
			return null;
		}

		$percent = Helpers::to_float( $map[ $key ] );
		return $percent > 0 ? $percent : null;
	}

	/**
	 * ذخیره درصدهای سفارشی کانال‌ها — فقط کانال‌هایی که مقدار مثبت دارند.
	 *
	 * @param array $map آرایه [channel_id => percent]؛ مقادیر خالی/صفر حذف می‌شوند.
	 */
	public function set_channel_percents( int $seller_id, array $map ): void {
		$clean = array();
		foreach ( $map as $channel_id => $percent ) {
			$channel_id = (int) $channel_id;
			$percent    = Helpers::to_float( $percent );
			if ( $channel_id > 0 && $percent > 0 ) {
				$clean[ (string) $channel_id ] = round( $percent, 3 );
			}
		}

		if ( empty( $clean ) ) {
			delete_user_meta( $seller_id, 'bespari_commission_channels' );
			return;
		}

		update_user_meta( $seller_id, 'bespari_commission_channels', $clean );
	}

	/**
	 * همه بازاریاب‌ها برای select.
	 *
	 * @return SellerModel[]
	 */
	public function all(): array {
		$users = get_users( array( 'role' => 'bespari_seller', 'number' => 200 ) );
		$out   = array();
		foreach ( $users as $u ) {
			$out[] = SellerModel::from_user( $u );
		}
		return $out;
	}
}
