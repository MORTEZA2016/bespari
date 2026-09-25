<?php
/**
 * Repository محصولات.
 *
 * @package Bespari\Modules\Product
 */

namespace Bespari\Modules\Product;

use Bespari\Support\BaseRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductRepository extends BaseRepository {

	protected string $table       = 'products';
	protected string $model_class = ProductModel::class;

	/**
	 * دریافت لیست محصولات با صفحه‌بندی و جستجو.
	 *
	 * @param array  $args {
	 *     @type string $search    عبارت جستجو.
	 *     @type int    $brand_id  فیلتر برند.
	 *     @type int    $status    فیلتر وضعیت.
	 *     @type int    $per_page  تعداد در صفحه.
	 *     @type int    $page      شماره صفحه.
	 * }
	 * @return array{items:ProductModel[],total:int}
	 */
	public function paginate( array $args = array() ): array {
		global $wpdb;

		$table     = $this->table();
		$brands    = Helpers::table( 'brands' );
		$search    = $args['search'] ?? '';
		$brand_id  = (int) ( $args['brand_id'] ?? 0 );
		$status    = isset( $args['status'] ) ? (int) $args['status'] : -1;
		$per_page  = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page      = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset    = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$values = array();

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(p.name LIKE %s OR p.sku LIKE %s OR p.product_code LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( $brand_id > 0 ) {
			$where[]  = 'p.brand_id = %d';
			$values[] = $brand_id;
		}

		if ( $status >= 0 ) {
			$where[]  = 'p.status = %d';
			$values[] = $status;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} p WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ); // phpcs:ignore

		$rows_sql = "SELECT p.*, b.name AS brand_name
					 FROM {$table} p
					 LEFT JOIN {$brands} b ON b.id = p.brand_id
					 WHERE {$where_sql}
					 ORDER BY p.id DESC
					 LIMIT %d OFFSET %d";

		$query_values   = array_merge( $values, array( $per_page, $offset ) );
		$rows           = $wpdb->get_results( $wpdb->prepare( $rows_sql, $query_values ) ); // phpcs:ignore

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = ProductModel::from_row( $row );
		}

		return array( 'items' => $items, 'total' => $total );
	}

	/**
	 * دریافت محصول با نام برند و نگاشت کانال‌ها.
	 *
	 * @param int $id آی‌دی.
	 * @return ProductModel|null
	 */
	public function get_full( int $id ): ?ProductModel {
		global $wpdb;

		$table  = $this->table();
		$brands = Helpers::table( 'brands' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.*, b.name AS brand_name FROM {$table} p LEFT JOIN {$brands} b ON b.id = p.brand_id WHERE p.id = %d", // phpcs:ignore
				$id
			)
		);

		if ( ! $row ) {
			return null;
		}

		$model           = ProductModel::from_row( $row );
		$model->channels = $this->get_channel_mappings( $id );

		return $model;
	}

	/**
	 * نگاشت‌های کانال یک محصول.
	 *
	 * @param int $product_id آی‌دی محصول.
	 * @return array
	 */
	public function get_channel_mappings( int $product_id ): array {
		global $wpdb;

		$pc       = Helpers::table( 'product_channel' );
		$channels = Helpers::table( 'channels' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pc.*, c.name AS channel_name
				 FROM {$pc} pc
				 LEFT JOIN {$channels} c ON c.id = pc.channel_id
				 WHERE pc.product_id = %d", // phpcs:ignore
				$product_id
			)
		) ?: array();
	}

	/**
	 * ذخیره نگاشت محصول-کانال (upsert).
	 *
	 * @param int   $product_id آی‌دی محصول.
	 * @param int   $channel_id آی‌دی کانال.
	 * @param array $data       داده‌ها.
	 */
	public function save_channel_mapping( int $product_id, int $channel_id, array $data ): void {
		global $wpdb;
		$table = Helpers::table( 'product_channel' );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND channel_id = %d", // phpcs:ignore
				$product_id,
				$channel_id
			)
		);

		$data['product_id'] = $product_id;
		$data['channel_id'] = $channel_id;
		$data['updated_at'] = Helpers::now();

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
		} else {
			$wpdb->insert( $table, $data );
		}
	}

	/**
	 * حذف نگاشت محصول-کانال.
	 *
	 * @param int $product_id آی‌دی محصول.
	 * @param int $channel_id آی‌دی کانال.
	 */
	public function delete_channel_mapping( int $product_id, int $channel_id ): void {
		global $wpdb;
		$wpdb->delete( Helpers::table( 'product_channel' ), array(
			'product_id' => $product_id,
			'channel_id' => $channel_id,
		) );
	}

	/**
	 * جستجوی محصول بر اساس آی‌دی ووکامرس.
	 *
	 * @param int $wc_id آی‌دی محصول ووکامرس.
	 * @return ProductModel|null
	 */
	public function get_by_wc_id( int $wc_id ) {
		$rows = $this->all( array( 'wc_product_id' => $wc_id ) );

		return ! empty( $rows ) ? ProductModel::from_row( $rows[0] ) : null;
	}
}
