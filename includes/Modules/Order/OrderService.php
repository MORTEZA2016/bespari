<?php
/**
 * سرویس سفارشات.
 *
 * @package Bespari\Modules\Order
 */

namespace Bespari\Modules\Order;

use Bespari\Modules\Channel\ChannelRepository;
use Bespari\Modules\Finance\Calculator;
use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderService {

	private OrderRepository $repo;
	private Calculator $calc;

	public function __construct() {
		$this->repo = new OrderRepository();
		$this->calc = new Calculator();
	}

	/**
	 * لیست صفحه‌بندی‌شده (اعمال فیلتر seller برای نقش بازاریاب).
	 */
	public function list( array $args = array() ): array {
		$args = $this->apply_seller_scope( $args );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters  = array_intersect_key( $args, array_flip( array(
			'search', 'channel_id', 'brand_id', 'seller_id', 'status',
			'payment_status', 'settlement_status', 'sale_type', 'date_from', 'date_to',
		) ) );
		return $this->repo->paginate( $filters, $page, $per_page );
	}

	public function get( int $id ): ?OrderModel {
		return $this->repo->get_full( $id );
	}

	/**
	 * ایجاد سفارش (محاسبه snapshot زنده).
	 *
	 * @param array $input
	 * @return int|\WP_Error
	 */
	public function create( array $input ) {
		$data = $this->sanitize( $input );

		if ( empty( $data['channel_id'] ) ) {
			return new \WP_Error( 'missing_channel', __( 'کانال الزامی است.', 'bespari-core' ) );
		}
		if ( empty( $data['items'] ) ) {
			return new \WP_Error( 'missing_items', __( 'حداقل یک محصول انتخاب کنید.', 'bespari-core' ) );
		}

		// فیلتر seller برای بازاریاب.
		if ( $this->is_seller_user() ) {
			$data['seller_id'] = get_current_user_id();
		}

		if ( empty( $data['order_number'] ) ) {
			$data['order_number'] = Helpers::generate_order_number();
		}

		$result = $this->calc->calculate_order(
			(int) $data['channel_id'],
			$data['sale_type'],
			(int) ( $data['seller_id'] ?? 0 ),
			$data['items']
		);

		$now  = Helpers::now();
		$ordered_at = ! empty( $data['ordered_at'] ) ? sanitize_text_field( $data['ordered_at'] ) : $now;

		// snapshot الویت تولید از کانال + روز تولید (همان روز ثبت).
		$channel = ( new ChannelRepository() )->get_by_id( (int) $data['channel_id'] );
		$production_date = substr( (string) $ordered_at, 0, 10 );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $production_date ) ) {
			$production_date = current_time( 'Y-m-d' );
		}

		$row  = array(
			'order_number'        => sanitize_text_field( $data['order_number'] ),
			'channel_id'          => (int) $data['channel_id'],
			'brand_id'            => (int) ( $data['brand_id'] ?? 0 ),
			'seller_id'           => (int) ( $data['seller_id'] ?? 0 ),
			'customer_name'       => sanitize_text_field( $data['customer_name'] ?? '' ),
			'customer_phone'      => sanitize_text_field( $data['customer_phone'] ?? '' ),
			'customer_address'    => sanitize_textarea_field( $data['customer_address'] ?? '' ),
			'sale_type'           => sanitize_key( $data['sale_type'] ?? 'cash' ),
			'status'              => sanitize_key( $data['status'] ?? 'pending' ),
			'payment_status'      => sanitize_key( $data['payment_status'] ?? 'unpaid' ),
			'settlement_status'   => 'pending',
			'production_priority' => $channel ? (int) $channel->production_priority : 0,
			'production_date'     => $production_date,
			'ordered_at'          => $ordered_at,
			'due_at'              => ! empty( $data['due_at'] ) ? sanitize_text_field( $data['due_at'] ) : null,
			'total_gross'         => $result['gross'],
			'total_net'           => $result['net'],
			'total_profit'        => $result['profit'],
			'rule_version'        => $result['rule_version'],
			'pricing_snapshot'    => wp_json_encode( $result['pricing_snapshot'], JSON_UNESCAPED_UNICODE ),
			'financials_snapshot' => wp_json_encode( $result['financials_snapshot'], JSON_UNESCAPED_UNICODE ),
			'notes'               => sanitize_textarea_field( $data['notes'] ?? '' ),
			'created_at'          => $now,
			'updated_at'          => $now,
		);

		$id = $this->repo->insert( $row );
		if ( ! $id ) {
			return new \WP_Error( 'insert_failed', __( 'خطا در ایجاد سفارش.', 'bespari-core' ) );
		}

		$this->repo->save_items( $id, $result['items_breakdown'] );
		AuditLog::created( 'order', $id, $row );

		/**
		 * هوک پس از ایجاد سفارش — ماژول پورسانت روی آن کمیسیون ثبت می‌کند.
		 */
		do_action( 'bespari_order_created', $id, $row );

		return $id;
	}

	/**
	 * به‌روزرسانی سفارش (رد اگر settled/paid).
	 *
	 * @param int   $id
	 * @param array $input
	 * @return bool|\WP_Error
	 */
	public function update( int $id, array $input ) {
		$existing = $this->repo->get_by_id( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}
		if ( ! $existing->is_editable() ) {
			return new \WP_Error( 'not_editable', __( 'سفارش تسویه یا پرداخت‌شده قابل ویرایش نیست.', 'bespari-core' ) );
		}
		if ( $this->is_seller_user() && (int) $existing->seller_id !== get_current_user_id() ) {
			return new \WP_Error( 'forbidden', __( 'دسترسی ندارید.', 'bespari-core' ) );
		}

		$data = $this->sanitize( $input );

		// اگر آیتم‌ها ارسال شده‌اند، محاسبه مجدد snapshot.
		$recalc = ! empty( $data['items'] );
		$row    = array(
			'channel_id'       => isset( $data['channel_id'] ) ? (int) $data['channel_id'] : $existing->channel_id,
			'brand_id'         => isset( $data['brand_id'] ) ? (int) $data['brand_id'] : $existing->brand_id,
			'seller_id'        => isset( $data['seller_id'] ) ? (int) $data['seller_id'] : $existing->seller_id,
			'customer_name'    => array_key_exists( 'customer_name', $data ) ? sanitize_text_field( $data['customer_name'] ) : $existing->customer_name,
			'customer_phone'   => array_key_exists( 'customer_phone', $data ) ? sanitize_text_field( $data['customer_phone'] ) : $existing->customer_phone,
			'customer_address' => array_key_exists( 'customer_address', $data ) ? sanitize_textarea_field( $data['customer_address'] ) : $existing->customer_address,
			'sale_type'        => isset( $data['sale_type'] ) ? sanitize_key( $data['sale_type'] ) : $existing->sale_type,
			'status'           => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : $existing->status,
			'payment_status'   => isset( $data['payment_status'] ) ? sanitize_key( $data['payment_status'] ) : $existing->payment_status,
			'ordered_at'       => ! empty( $data['ordered_at'] ) ? sanitize_text_field( $data['ordered_at'] ) : $existing->ordered_at,
			'due_at'           => array_key_exists( 'due_at', $data ) ? ( $data['due_at'] ? sanitize_text_field( $data['due_at'] ) : null ) : $existing->due_at,
			'notes'            => array_key_exists( 'notes', $data ) ? sanitize_textarea_field( $data['notes'] ) : $existing->notes,
			'updated_at'       => Helpers::now(),
		);

		if ( $this->is_seller_user() ) {
			$row['seller_id'] = get_current_user_id();
		}

		if ( $recalc ) {
			$result = $this->calc->calculate_order(
				(int) $row['channel_id'],
				$row['sale_type'],
				(int) $row['seller_id'],
				$data['items']
			);
			$row['total_gross']         = $result['gross'];
			$row['total_net']           = $result['net'];
			$row['total_profit']        = $result['profit'];
			$row['rule_version']        = $result['rule_version'];
			$row['pricing_snapshot']    = wp_json_encode( $result['pricing_snapshot'], JSON_UNESCAPED_UNICODE );
			$row['financials_snapshot'] = wp_json_encode( $result['financials_snapshot'], JSON_UNESCAPED_UNICODE );
		}

		$ok = $this->repo->update( $id, $row );
		if ( ! $ok ) {
			return new \WP_Error( 'update_failed', __( 'خطا در به‌روزرسانی سفارش.', 'bespari-core' ) );
		}

		if ( $recalc ) {
			$this->repo->save_items( $id, $result['items_breakdown'] );
		}

		AuditLog::updated( 'order', $id, $existing->to_array(), $row );

		do_action( 'bespari_order_updated', $id, $row );

		return true;
	}

	/**
	 * تغییر وضعیت سفارش.
	 */
	/**
	 * ثبت کد ارجاع تسویه سفارش (حالت فاکتور به فاکتور) → تسویه شده.
	 */
	public function mark_settled( int $id, string $settlement_ref ): bool|\WP_Error {
		$existing = $this->repo->get_by_id( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}
		if ( 'settled' === $existing->settlement_status ) {
			return new \WP_Error( 'already_settled', __( 'این سفارش قبلاً تسویه شده است.', 'bespari-core' ) );
		}

		$notes = trim( (string) $existing->notes );
		$ref_note = __( 'کد ارجاع تسویه:', 'bespari-core' ) . ' ' . sanitize_text_field( $settlement_ref );
		$new_notes = '' !== $notes ? $notes . "\n" . $ref_note : $ref_note;

		$ok = $this->repo->update( $id, array(
			'settlement_status' => 'settled',
			'notes'             => $new_notes,
			'updated_at'        => Helpers::now(),
		) );

		if ( $ok ) {
			AuditLog::log( 'order_settled', 'order', $id, array( 'settlement_status' => $existing->settlement_status ), array( 'settlement_status' => 'settled', 'settlement_ref' => sanitize_text_field( $settlement_ref ) ) );
			return true;
		}

		return new \WP_Error( 'settle_failed', __( 'خطا در ثبت تسویه.', 'bespari-core' ) );
	}

	public function update_status( int $id, string $status ): bool|\WP_Error {
		$existing = $this->repo->get_by_id( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}
		$allowed = array( 'pending', 'confirmed', 'produced', 'shipped', 'delivered', 'cancelled', 'paid' );
		$status  = sanitize_key( $status );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new \WP_Error( 'invalid_status', __( 'وضعیت نامعتبر.', 'bespari-core' ) );
		}

		$row = array( 'status' => $status, 'updated_at' => Helpers::now() );

		// زمان آماده‌سازی فقط برای وضعیت «تولید شده» ثبت می‌شود.
		if ( 'produced' === $status ) {
			$row['produced_at'] = Helpers::now();
		} elseif ( 'produced' === $existing->status ) {
			$row['produced_at'] = null;
		}

		$ok = $this->repo->update( $id, $row );
		if ( $ok ) {
			AuditLog::log( 'update_status', 'order', $id, array( 'status' => $existing->status ), array( 'status' => $status ) );
			if ( 'cancelled' === $status ) {
				/**
				 * کنسل شدن سفارش — پورسانت مرتبط باید کنسل شود.
				 */
				do_action( 'bespari_order_status_cancelled', $id );
			}

			if ( 'produced' === $status ) {
				/**
				 * آماده‌شدن سفارش در خط تولید — اعلان خودکار به بازاریاب.
				 */
				do_action( 'bespari_order_produced', $id, $existing );
			}

			// بطلان کش آمار (Optimization).
			do_action( 'bespari_order_updated', $id, $row );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در تغییر وضعیت.', 'bespari-core' ) );
	}

	public function delete( int $id ): bool|\WP_Error {
		$existing = $this->repo->get_by_id( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'سفارش یافت نشد.', 'bespari-core' ) );
		}
		if ( ! $existing->is_editable() ) {
			return new \WP_Error( 'not_editable', __( 'سفارش تسویه‌شده قابل حذف نیست.', 'bespari-core' ) );
		}
		if ( $this->is_seller_user() && (int) $existing->seller_id !== get_current_user_id() ) {
			return new \WP_Error( 'forbidden', __( 'دسترسی ندارید.', 'bespari-core' ) );
		}
		global $wpdb;
		$wpdb->delete( Helpers::table( 'order_items' ), array( 'order_id' => $id ) );
		$ok = $this->repo->delete( $id );
		if ( $ok ) {
			AuditLog::deleted( 'order', $id, $existing->to_array() );
		}
		return $ok ? true : new \WP_Error( 'delete_failed', __( 'خطا در حذف سفارش.', 'bespari-core' ) );
	}

	private function sanitize( array $input ): array {
		$data = array();
		if ( isset( $input['order_number'] ) ) {
			$data['order_number'] = sanitize_text_field( $input['order_number'] );
		}
		if ( isset( $input['channel_id'] ) ) {
			$data['channel_id'] = (int) $input['channel_id'];
		}
		if ( isset( $input['brand_id'] ) ) {
			$data['brand_id'] = (int) $input['brand_id'];
		}
		if ( isset( $input['seller_id'] ) ) {
			$data['seller_id'] = (int) $input['seller_id'];
		}
		if ( isset( $input['customer_name'] ) ) {
			$data['customer_name'] = sanitize_text_field( $input['customer_name'] );
		}
		if ( isset( $input['customer_phone'] ) ) {
			$data['customer_phone'] = sanitize_text_field( $input['customer_phone'] );
		}
		if ( isset( $input['customer_address'] ) ) {
			$data['customer_address'] = sanitize_textarea_field( $input['customer_address'] );
		}
		if ( isset( $input['sale_type'] ) ) {
			$data['sale_type'] = sanitize_key( $input['sale_type'] );
		}
		if ( isset( $input['status'] ) ) {
			$data['status'] = sanitize_key( $input['status'] );
		}
		if ( isset( $input['payment_status'] ) ) {
			$data['payment_status'] = sanitize_key( $input['payment_status'] );
		}
		if ( isset( $input['ordered_at'] ) ) {
			$data['ordered_at'] = sanitize_text_field( $input['ordered_at'] );
		}
		if ( array_key_exists( 'due_at', $input ) ) {
			$data['due_at'] = $input['due_at'] ? sanitize_text_field( $input['due_at'] ) : null;
		}
		if ( isset( $input['notes'] ) ) {
			$data['notes'] = sanitize_textarea_field( $input['notes'] );
		}
		if ( isset( $input['items'] ) && is_array( $input['items'] ) ) {
			$clean = array();
			foreach ( $input['items'] as $it ) {
				if ( empty( $it['product_id'] ) ) {
					continue;
				}
				$clean[] = array(
					'product_id' => (int) $it['product_id'],
					'quantity'   => max( 1, (int) ( $it['quantity'] ?? 1 ) ),
				);
			}
			$data['items'] = $clean;
		}
		return $data;
	}

	private function is_seller_user(): bool {
		$user = wp_get_current_user();
		return $user && in_array( 'bespari_seller', (array) $user->roles, true );
	}

	private function apply_seller_scope( array $args ): array {
		if ( $this->is_seller_user() ) {
			$args['seller_id'] = get_current_user_id();
		}
		return $args;
	}
}
