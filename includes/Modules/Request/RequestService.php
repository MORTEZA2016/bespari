<?php
/**
 * سرویس درخواست‌ها — گردش کار درخواست (بازاریاب/کارمند → ادمین).
 *
 * @package Bespari\Modules\Request
 */

namespace Bespari\Modules\Request;

use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RequestService {

	private RequestRepository $repo;

	public function __construct() {
		$this->repo = new RequestRepository();
	}

	/**
	 * آیا کاربر فعلی بازاریاب (بدون cap مدیریت) است؟
	 */
	private function is_seller_user(): bool {
		$user = wp_get_current_user();
		return $user && in_array( 'bespari_seller', (array) $user->roles, true ) && ! current_user_can( 'bespari_manage_requests' );
	}

	/**
	 * لیست صفحه‌بندی‌شده — بازاریاب فقط درخواست‌های خودش.
	 */
	public function list( array $args = array() ): array {
		if ( $this->is_seller_user() ) {
			$args['requester_id'] = get_current_user_id();
		}
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters  = array_intersect_key( $args, array_flip( array( 'search', 'type', 'status', 'requester_id', 'date_from', 'date_to' ) ) );
		return $this->repo->paginate( $filters, $page, $per_page );
	}

	public function get( int $id ): ?RequestModel {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return null;
		}
		if ( $this->is_seller_user() && (int) $m->requester_id !== get_current_user_id() ) {
			return null;
		}
		return $m;
	}

	/**
	 * ثبت درخواست جدید (ادمین یا بازاریاب برای خودش).
	 *
	 * @param array $input type, title, description, product_id, order_id
	 * @return int|\WP_Error
	 */
	public function save( array $input ) {
		$type = isset( $input['type'] ) ? sanitize_key( wp_unslash( $input['type'] ) ) : 'other';
		if ( ! in_array( $type, array( 'stock', 'price', 'discount', 'other' ), true ) ) {
			$type = 'other';
		}

		$title       = isset( $input['title'] ) ? sanitize_text_field( wp_unslash( $input['title'] ) ) : '';
		$description = isset( $input['description'] ) ? sanitize_textarea_field( wp_unslash( $input['description'] ) ) : '';
		$product_id  = (int) ( $input['product_id'] ?? 0 );
		$order_id    = (int) ( $input['order_id'] ?? 0 );

		if ( '' === $title ) {
			return new \WP_Error( 'missing_title', __( 'عنوان درخواست الزامی است.', 'bespari-core' ) );
		}

		// بازاریاب فقط برای خودش ثبت می‌کند.
		$requester_id = get_current_user_id();

		$now = Helpers::now();
		$id  = $this->repo->insert( array(
			'type'         => $type,
			'title'        => $title,
			'description'  => $description,
			'requester_id' => $requester_id,
			'assignee_id'  => 0,
			'status'       => 'pending',
			'product_id'   => $product_id,
			'order_id'     => $order_id,
			'created_at'   => $now,
			'updated_at'   => $now,
		) );

		if ( $id ) {
			AuditLog::created( 'request', $id, array( 'type' => $type, 'title' => $title, 'requester_id' => $requester_id ) );
		}

		return $id ?: new \WP_Error( 'insert_failed', __( 'خطا در ثبت درخواست.', 'bespari-core' ) );
	}

	/**
	 * تایید درخواست با یادداشت پاسخ.
	 */
	public function approve( int $id, string $resolution_notes = '' ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'درخواست یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' !== $m->status ) {
			return new \WP_Error( 'invalid_status', __( 'فقط درخواست در انتظار قابل تایید است.', 'bespari-core' ) );
		}
		$now = Helpers::now();
		$ok  = $this->repo->update( $id, array(
			'status'           => 'approved',
			'assignee_id'      => get_current_user_id(),
			'resolved_at'      => $now,
			'resolution_notes' => sanitize_textarea_field( $resolution_notes ),
			'updated_at'       => $now,
		) );
		if ( $ok ) {
			AuditLog::log( 'approve', 'request', $id, array( 'status' => 'pending' ), array( 'status' => 'approved' ) );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در تایید درخواست.', 'bespari-core' ) );
	}

	/**
	 * رد درخواست با یادداشت پاسخ.
	 */
	public function reject( int $id, string $resolution_notes = '' ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'درخواست یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' !== $m->status ) {
			return new \WP_Error( 'invalid_status', __( 'فقط درخواست در انتظار قابل رد است.', 'bespari-core' ) );
		}
		$now = Helpers::now();
		$ok  = $this->repo->update( $id, array(
			'status'           => 'rejected',
			'assignee_id'      => get_current_user_id(),
			'resolved_at'      => $now,
			'resolution_notes' => sanitize_textarea_field( $resolution_notes ),
			'updated_at'       => $now,
		) );
		if ( $ok ) {
			AuditLog::log( 'reject', 'request', $id, array( 'status' => 'pending' ), array( 'status' => 'rejected' ) );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در رد درخواست.', 'bespari-core' ) );
	}
}
