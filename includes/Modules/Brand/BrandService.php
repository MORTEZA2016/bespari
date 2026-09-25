<?php
/**
 * سرویس لاجیک برندها.
 *
 * @package Bespari\Modules\Brand
 */

namespace Bespari\Modules\Brand;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BrandService {

	private BrandRepository $repo;

	public function __construct() {
		$this->repo = new BrandRepository();
	}

	/**
	 * لیست همه برندها.
	 *
	 * @param bool $only_active فقط فعال.
	 * @return BrandModel[]
	 */
	public function list( bool $only_active = false ): array {
		return $this->repo->get_all( $only_active );
	}

	/**
	 * دریافت یک برند.
	 *
	 * @param int $id آی‌دی.
	 * @return BrandModel|null
	 */
	public function get( int $id ): ?BrandModel {
		return $this->repo->get_by_id( $id );
	}

	/**
	 * ایجاد برند.
	 *
	 * @param array $input ورودی فرم.
	 * @return int|WP_Error
	 */
	public function create( array $input ) {
		$data = $this->sanitize( $input );

		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'نام برند الزامی است.', 'bespari-core' ) );
		}

		$data['slug']       = Helpers::unique_slug( $data['name'], 'brands' );
		$data['created_at'] = Helpers::now();
		$data['updated_at'] = Helpers::now();

		$channel_ids = $data['_channel_ids'] ?? array();
		unset( $data['_channel_ids'] );

		$id = $this->repo->insert( $data );

		if ( $id ) {
			if ( ! empty( $channel_ids ) ) {
				$this->repo->save_brand_channels( $id, $channel_ids );
			}
			\Bespari\Support\AuditLog::created( 'brand', $id, $data );
		}

		return $id ?: new \WP_Error( 'insert_failed', __( 'خطا در ایجاد برند.', 'bespari-core' ) );
	}

	/**
	 * به‌روزرسانی برند.
	 *
	 * @param int   $id    آی‌دی.
	 * @param array $input ورودی.
	 * @return bool|WP_Error
	 */
	public function update( int $id, array $input ) {
		$existing = $this->repo->get_by_id( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'برند یافت نشد.', 'bespari-core' ) );
		}

		$data = $this->sanitize( $input );

		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'نام برند الزامی است.', 'bespari-core' ) );
		}

		if ( $data['name'] !== $existing->name ) {
			$data['slug'] = Helpers::unique_slug( $data['name'], 'brands', $id );
		} else {
			unset( $data['slug'] );
		}

		$data['updated_at'] = Helpers::now();

		$channel_ids = $data['_channel_ids'] ?? null;
		unset( $data['_channel_ids'] );

		$ok = $this->repo->update( $id, $data );

		if ( $ok && null !== $channel_ids ) {
			$this->repo->save_brand_channels( $id, $channel_ids );
		}

		if ( $ok ) {
			\Bespari\Support\AuditLog::updated( 'brand', $id, $existing->to_array(), $data );
		}

		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در به‌روزرسانی برند.', 'bespari-core' ) );
	}

	/**
	 * حذف برند.
	 *
	 * @param int $id آی‌دی.
	 * @return bool|WP_Error
	 */
	public function delete( int $id ) {
		$existing = $this->repo->get_by_id( $id );

		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'برند یافت نشد.', 'bespari-core' ) );
		}

		$ok = $this->repo->delete( $id );

		if ( $ok ) {
			\Bespari\Support\AuditLog::deleted( 'brand', $id, $existing->to_array() );
			global $wpdb;
			$wpdb->delete( Helpers::table( 'brand_channels' ), array( 'brand_id' => $id ) );
		}

		return $ok ? true : new \WP_Error( 'delete_failed', __( 'خطا در حذف برند.', 'bespari-core' ) );
	}

	/**
	 * پاک‌سازی ورودی.
	 *
	 * @param array $input ورودی خام.
	 * @return array
	 */
	private function sanitize( array $input ): array {
		$data = array();

		$data['name']          = sanitize_text_field( $input['name'] ?? '' );
		$data['status']        = isset( $input['status'] ) ? (int) $input['status'] : 1;
		$data['logo']          = esc_url_raw( $input['logo'] ?? '' );
		$data['description']   = sanitize_textarea_field( $input['description'] ?? '' );
		$data['owner_user_id'] = (int) ( $input['owner_user_id'] ?? 0 );

		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$data['meta'] = wp_json_encode( $input['meta'], JSON_UNESCAPED_UNICODE );
		}

		if ( isset( $input['channel_ids'] ) && is_array( $input['channel_ids'] ) ) {
			$data['_channel_ids'] = $input['channel_ids'];
		}

		return $data;
	}
}
