<?php
/**
 * سرویس لاجیک محصولات + سینک با ووکامرس.
 *
 * @package Bespari\Modules\Product
 */

namespace Bespari\Modules\Product;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductService {

	private ProductRepository $repo;

	public function __construct() {
		$this->repo = new ProductRepository();
	}

	/**
	 * لیست صفحه‌بندی‌شده محصولات.
	 *
	 * @param array $args پارامترها.
	 * @return array{items:ProductModel[],total:int}
	 */
	public function list( array $args = array() ): array {
		return $this->repo->paginate( $args );
	}

	/**
	 * دریافت محصول کامل.
	 *
	 * @param int $id آی‌دی.
	 * @return ProductModel|null
	 */
	public function get( int $id ): ?ProductModel {
		return $this->repo->get_full( $id );
	}

	/**
	 * ایجاد محصول.
	 *
	 * @param array $input ورودی.
	 * @return int|WP_Error
	 */
	public function create( array $input ) {
		$data = $this->sanitize( $input );

		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'نام محصول الزامی است.', 'bespari-core' ) );
		}

		$data['created_at'] = Helpers::now();
		$data['updated_at'] = Helpers::now();

		$channels = $data['_channels'] ?? array();
		unset( $data['_channels'] );

		$id = $this->repo->insert( $data );

		if ( $id ) {
			foreach ( $channels as $channel_id => $map ) {
				$this->repo->save_channel_mapping( $id, (int) $channel_id, $map );
			}
			\Bespari\Support\AuditLog::created( 'product', $id, $data );
		}

		return $id ?: new \WP_Error( 'insert_failed', __( 'خطا در ایجاد محصول.', 'bespari-core' ) );
	}

	/**
	 * به‌روزرسانی محصول.
	 *
	 * @param int   $id    آی‌دی.
	 * @param array $input ورودی.
	 * @return bool|WP_Error
	 */
	public function update( int $id, array $input ) {
		$existing = $this->repo->find( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'محصول یافت نشد.', 'bespari-core' ) );
		}

		$data = $this->sanitize( $input );

		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'نام محصول الزامی است.', 'bespari-core' ) );
		}

		$data['updated_at'] = Helpers::now();

		$channels = $data['_channels'] ?? null;
		unset( $data['_channels'] );

		$ok = $this->repo->update( $id, $data );

		if ( $ok && null !== $channels ) {
			foreach ( $channels as $channel_id => $map ) {
				$this->repo->save_channel_mapping( $id, (int) $channel_id, $map );
			}
		}

		if ( $ok ) {
			\Bespari\Support\AuditLog::updated( 'product', $id, (array) $existing, $data );
		}

		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در به‌روزرسانی محصول.', 'bespari-core' ) );
	}

	/**
	 * حذف محصول.
	 *
	 * @param int $id آی‌دی.
	 * @return bool|WP_Error
	 */
	public function delete( int $id ) {
		$existing = $this->repo->find( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'محصول یافت نشد.', 'bespari-core' ) );
		}

		$ok = $this->repo->delete( $id );

		if ( $ok ) {
			\Bespari\Support\AuditLog::deleted( 'product', $id, (array) $existing );
			global $wpdb;
			$wpdb->delete( Helpers::table( 'product_channel' ), array( 'product_id' => $id ) );
		}

		return $ok ? true : new \WP_Error( 'delete_failed', __( 'خطا در حذف محصول.', 'bespari-core' ) );
	}

	/**
	 * همگام‌سازی محصولات ووکامرس با جدول ERP (فقط در صورت فعال بودن ووکامرس).
	 *
	 * @return int تعداد محصولاتی که ایجاد/به‌روز شدند.
	 */
	public function sync_from_woocommerce(): int {
		if ( ! Helpers::woocommerce_is_active() || ! class_exists( '\WC_Product_Query' ) ) {
			return 0;
		}

		$processed = 0;
		$page      = 1;
		$batch     = 50;

		do {
			$products = wc_get_products( array(
				'limit'   => $batch,
				'page'    => $page,
				'status'  => 'publish',
				'orderby' => 'id',
				'order'   => 'ASC',
			) );

			if ( empty( $products ) ) {
				break;
			}

			foreach ( $products as $wc_product ) {
				if ( ! $wc_product instanceof \WC_Product ) {
					continue;
				}
				$this->upsert_from_wc( $wc_product );
				++$processed;
			}

			++$page;
		} while ( count( $products ) === $batch );

		return $processed;
	}

	/**
	 * ایجاد یا به‌روزرسانی رکورد ERP از یک محصول ووکامرس.
	 *
	 * @param \WC_Product $wc_product محصول ووکامرس.
	 * @return int آی‌دی رکورد ERP.
	 */
	private function upsert_from_wc( \WC_Product $wc_product ): int {
		$wc_id    = $wc_product->get_id();
		$existing = $this->repo->get_by_wc_id( $wc_id );

		$base_data = array(
			'name'          => $wc_product->get_name(),
			'sku'           => (string) $wc_product->get_sku(),
			'wc_product_id' => $wc_id,
			'base_price'    => (float) $wc_product->get_price(),
			'stock'         => (int) $wc_product->get_stock_quantity(),
			'description'   => wp_strip_all_tags( $wc_product->get_short_description() ),
			'updated_at'    => Helpers::now(),
		);

		// تلاش برای اتصال خودکار برند (از taxonomies ووکامرس اگر موجود باشد).
		$brand_id = $this->resolve_brand_from_wc( $wc_product );
		if ( $brand_id ) {
			$base_data['brand_id'] = $brand_id;
		}

		if ( $existing ) {
			$this->repo->update( $existing->id, $base_data );

			return $existing->id;
		}

		$base_data['status']     = 1;
		$base_data['created_at'] = Helpers::now();

		return $this->repo->insert( $base_data );
	}

	/**
	 * تلاش برای پیدا کردن برند از taxonomies ووکامرس (pa_brand یا product_cat).
	 *
	 * @param \WC_Product $wc_product محصول.
	 * @return int آی‌دی برند ERP یا 0.
	 */
	private function resolve_brand_from_wc( \WC_Product $wc_product ): int {
		$term_ids = wp_get_post_terms( $wc_product->get_id(), array( 'pa_brand', 'product_brand' ), array( 'fields' => 'ids' ) );

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return 0;
		}

		$term = get_term( $term_ids[0] );
		if ( ! $term || is_wp_error( $term ) ) {
			return 0;
		}

		global $wpdb;
		$brands_table = Helpers::table( 'brands' );
		$brand_id     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$brands_table} WHERE name = %s LIMIT 1", // phpcs:ignore
				$term->name
			)
		);

		return $brand_id ? (int) $brand_id : 0;
	}

	/**
	 * پاک‌سازی ورودی فرم.
	 *
	 * @param array $input ورودی خام.
	 * @return array
	 */
	private function sanitize( array $input ): array {
		$data = array();

		$data['name']          = sanitize_text_field( $input['name'] ?? '' );
		$data['sku']           = sanitize_text_field( $input['sku'] ?? '' );
		$data['product_code']  = sanitize_text_field( $input['product_code'] ?? '' );
		$data['wc_product_id'] = (int) ( $input['wc_product_id'] ?? 0 );
		$data['brand_id']      = (int) ( $input['brand_id'] ?? 0 );
		$data['category_id']   = (int) ( $input['category_id'] ?? 0 );
		$data['base_price']    = Helpers::to_float( $input['base_price'] ?? 0 );
		$data['purchase_price'] = Helpers::to_float( $input['purchase_price'] ?? 0 );
		$data['finished_cost'] = Helpers::to_float( $input['finished_cost'] ?? 0 );
		$data['stock']         = (int) ( $input['stock'] ?? 0 );
		$data['status']        = isset( $input['status'] ) ? (int) $input['status'] : 1;
		$data['description']   = sanitize_textarea_field( $input['description'] ?? '' );

		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$data['meta'] = wp_json_encode( $input['meta'], JSON_UNESCAPED_UNICODE );
		}

		// نگاشت کانال‌ها.
		if ( isset( $input['channels'] ) && is_array( $input['channels'] ) ) {
			$clean = array();
			foreach ( $input['channels'] as $cid => $map ) {
				$clean[ (int) $cid ] = array(
					'variation_code'      => sanitize_text_field( $map['variation_code'] ?? '' ),
					'external_product_id' => sanitize_text_field( $map['external_product_id'] ?? '' ),
					'external_seller_id'  => sanitize_text_field( $map['external_seller_id'] ?? '' ),
					'product_url'         => esc_url_raw( $map['product_url'] ?? '' ),
					'channel_price'       => Helpers::to_float( $map['channel_price'] ?? 0 ),
					'channel_stock'       => (int) ( $map['channel_stock'] ?? 0 ),
					'is_active'           => isset( $map['is_active'] ) ? (int) $map['is_active'] : 1,
				);
			}
			$data['_channels'] = $clean;
		}

		return $data;
	}
}
