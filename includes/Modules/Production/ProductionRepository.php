<?php
/**
 * Repository خط تولید — کوئری‌های سفارشات قابل‌تولید بر اساس روز.
 *
 * @package Bespari\Modules\Production
 */

namespace Bespari\Modules\Production;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductionRepository extends BaseRepository {

	protected string $table       = 'orders';
	protected string $model_class = \Bespari\Modules\Order\OrderModel::class;

	/**
	 * وضعیت‌هایی که در خط تولید نمایش داده می‌شوند (هنوز تولید/ارسال نشده‌اند).
	 */
	private const ACTIVE_STATUSES = array( 'pending', 'confirmed', 'produced' );

	/**
	 * سفارش‌های یک روز تولید.
	 *
	 * @param string $date روز با فرمت Y-m-d.
	 * @return array ردیف‌های خام شامل channel_name، brand_name، seller_name، shipping_window.
	 */
	public function list_by_date( string $date ): array {
		global $wpdb;

		$table = $this->table();
		$ch    = Helpers::table( 'channels' );
		$br    = Helpers::table( 'brands' );
		$users = $wpdb->users;

		$sql = $wpdb->prepare(
			"SELECT o.*, c.name AS channel_name, c.shipping_window, b.name AS brand_name, u.display_name AS seller_name
			 FROM {$table} o
			 LEFT JOIN {$ch} c ON c.id = o.channel_id
			 LEFT JOIN {$br} b ON b.id = o.brand_id
			 LEFT JOIN {$users} u ON u.ID = o.seller_id
			 WHERE ( o.production_date = %s OR ( o.production_date IS NULL AND DATE( o.ordered_at ) = %s ) )
			 AND o.status IN ( 'pending', 'confirmed', 'produced' )
			 ORDER BY o.production_moved DESC, o.production_priority ASC, o.ordered_at ASC, o.id ASC", // phpcs:ignore
			$date,
			$date
		);

		return (array) $wpdb->get_results( $sql ); // phpcs:ignore
	}

	/**
	 * آیتم‌های چند سفارش در یک کوئری.
	 *
	 * @param array $order_ids شناسه‌ها.
	 * @return array کلید: order_id، مقدار: آرایه‌ای از آیتم‌ها.
	 */
	public function get_items_map( array $order_ids ): array {
		global $wpdb;

		if ( empty( $order_ids ) ) {
			return array();
		}

		$ids    = array_map( 'intval', $order_ids );
		$ids    = array_filter( $ids );
		$place  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$table  = Helpers::table( 'order_items' );

		$sql   = $wpdb->prepare( "SELECT order_id, product_name, quantity FROM {$table} WHERE order_id IN ( {$place} ) ORDER BY id ASC", $ids ); // phpcs:ignore
		$rows  = (array) $wpdb->get_results( $sql ); // phpcs:ignore

		$map = array();
		foreach ( $rows as $r ) {
			$map[ (int) $r->order_id ][] = array(
				'name'     => (string) $r->product_name,
				'quantity' => (int) $r->quantity,
			);
		}

		return $map;
	}

	/**
	 * ثبت روز تولید و پرچم «مهمان» برای یک سفارش.
	 *
	 * @param int    $order_id شناسه سفارش.
	 * @param string $date     روز مقصد.
	 * @param bool   $moved    منتقل‌شده توسط مدیر تولید.
	 * @return bool
	 */
	public function set_schedule( int $order_id, string $date, bool $moved = true ): bool {
		return $this->update( $order_id, array(
			'production_date'  => $date,
			'production_moved' => $moved ? 1 : 0,
			'updated_at'       => Helpers::now(),
		) );
	}
}
