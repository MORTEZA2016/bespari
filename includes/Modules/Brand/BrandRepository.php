<?php
/**
 * Repository برندها.
 *
 * @package Bespari\Modules\Brand
 */

namespace Bespari\Modules\Brand;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BrandRepository extends BaseRepository {

	protected string $table       = 'brands';
	protected string $model_class = BrandModel::class;

	/**
	 * دریافت همه برندها.
	 *
	 * @param bool $only_active فقط فعال.
	 * @return BrandModel[]
	 */
	public function get_all( bool $only_active = false ): array {
		$where = $only_active ? array( 'status' => 1 ) : array();
		$rows  = $this->all( $where, 'name', 'ASC' );

		return $this->hydrate_with_channels( $rows );
	}

	/**
	 * دریافت برند بر اساس آی‌دی.
	 *
	 * @param int $id آی‌دی.
	 * @return BrandModel|null
	 */
	public function get_by_id( int $id ): ?BrandModel {
		$row = $this->find( $id );
		if ( ! $row ) {
			return null;
		}

		$model               = BrandModel::from_row( $row );
		$model->channel_ids  = $this->get_brand_channel_ids( $id );

		return $model;
	}

	/**
	 * کانال‌های فعال یک برند.
	 *
	 * @param int $brand_id آی‌دی برند.
	 * @return int[]
	 */
	public function get_brand_channel_ids( int $brand_id ): array {
		global $wpdb;
		$table = Helpers::table( 'brand_channels' );

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT channel_id FROM {$table} WHERE brand_id = %d AND is_active = 1", // phpcs:ignore
				$brand_id
			)
		);

		return $ids ? array_map( 'intval', $ids ) : array();
	}

	/**
	 * ذخیره نگاشت برند به کانال‌ها (جایگزینی کامل).
	 *
	 * @param int   $brand_id    آی‌دی برند.
	 * @param int[] $channel_ids آرایه کانال‌ها.
	 */
	public function save_brand_channels( int $brand_id, array $channel_ids ): void {
		global $wpdb;
		$table = Helpers::table( 'brand_channels' );

		$wpdb->delete( $table, array( 'brand_id' => $brand_id ) );

		$now = Helpers::now();
		foreach ( array_unique( array_map( 'intval', $channel_ids ) ) as $cid ) {
			if ( 0 === $cid ) {
				continue;
			}
			$wpdb->insert( $table, array(
				'brand_id'   => $brand_id,
				'channel_id' => $cid,
				'is_active'  => 1,
				'created_at' => $now,
			) );
		}
	}

	/**
	 * پر کردن کانال‌ها برای لیست برندها.
	 *
	 * @param array $rows ردیف‌های خام.
	 * @return BrandModel[]
	 */
	private function hydrate_with_channels( array $rows ): array {
		$models = array();
		foreach ( $rows as $row ) {
			$model              = BrandModel::from_row( $row );
			$model->channel_ids = $this->get_brand_channel_ids( $model->id );
			$models[]           = $model;
		}

		return $models;
	}
}
