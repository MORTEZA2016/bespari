<?php
/**
 * سرویس پورسانت.
 *
 * @package Bespari\Modules\Commission
 */

namespace Bespari\Modules\Commission;

use Bespari\Modules\Seller\SellerRepository;
use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommissionService {

	private CommissionRepository $repo;
	private SellerRepository $seller_repo;

	public function __construct() {
		$this->repo        = new CommissionRepository();
		$this->seller_repo = new SellerRepository();
	}

	public function list( array $args = array() ): array {
		$args = $this->apply_seller_scope( $args );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters  = array_intersect_key( $args, array_flip( array( 'seller_id', 'status', 'date_from', 'date_to', 'search' ) ) );
		return $this->repo->paginate( $filters, $page, $per_page );
	}

	public function get( int $id ): ?CommissionModel {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return null;
		}
		if ( $this->is_seller_user() && (int) $m->seller_id !== get_current_user_id() ) {
			return null;
		}
		return $m;
	}

	/**
	 * ثبت پورسانت برای یک سفارش (از snapshot سفارش).
	 *
	 * @param int $order_id
	 * @return int|\WP_Error آی‌دی کمیسیون یا WP_Error / 0 اگر skip
	 */
	public function record_for_order( int $order_id ) {
		global $wpdb;

		// اگر قبلاً ثبت شده skip.
		if ( $this->repo->find_by_order( $order_id ) ) {
			return 0;
		}

		$o_table = Helpers::table( 'orders' );
		$order   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$o_table} WHERE id = %d", $order_id ) ); // phpcs:ignore

		if ( ! $order || empty( $order->seller_id ) ) {
			return 0;
		}

		$seller_id = (int) $order->seller_id;
		$channel_id = (int) $order->channel_id;

		// درصد پورسانت: ۱) درصد سفارشی بازاریاب برای این کانال، ۲) درصد پورسانت کانال، ۳) پیش‌فرض سراسری.
		$percent = $this->seller_repo->get_channel_percent( $seller_id, $channel_id );

		if ( null === $percent ) {
			// درصد کانال — ملاک برای بازاریابانی که درصد سفارشی ندارند.
			$channel_percent = $wpdb->get_var( $wpdb->prepare( 'SELECT commission_percent FROM ' . Helpers::table( 'channels' ) . ' WHERE id = %d', $channel_id ) ); // phpcs:ignore
			if ( null !== $channel_percent && Helpers::to_float( $channel_percent ) > 0 ) {
				$percent = Helpers::to_float( $channel_percent );
			} else {
				$percent = (float) Helpers::get_setting( 'commission_default_percent', 5 );
			}
		}

		if ( $percent <= 0 ) {
			return 0;
		}

		$base_type = Helpers::get_setting( 'commission_base', 'net' );
		if ( ! in_array( $base_type, array( 'net', 'profit' ), true ) ) {
			$base_type = 'net';
		}

		// مبنا از financials_snapshot.
		$snap = array();
		if ( ! empty( $order->financials_snapshot ) ) {
			$decoded = json_decode( (string) $order->financials_snapshot, true );
			if ( is_array( $decoded ) ) {
				$snap = $decoded;
			}
		}

		$base_amount = 0.0;
		if ( 'profit' === $base_type ) {
			$base_amount = (float) ( $snap['profit'] ?? $order->total_profit ?? 0 );
		} else {
			$base_amount = (float) ( $snap['net'] ?? $order->total_net ?? 0 );
		}

		if ( $base_amount <= 0 ) {
			$base_amount = 0;
		}

		$amount = round( $base_amount * $percent / 100, 2 );

		$now = Helpers::now();
		$id  = $this->repo->insert( array(
			'order_id'    => $order_id,
			'seller_id'   => $seller_id,
			'base_amount' => $base_amount,
			'base_type'   => $base_type,
			'percent'     => $percent,
			'amount'      => $amount,
			'status'      => 'pending',
			'created_at'  => $now,
			'updated_at'  => $now,
		) );

		if ( $id ) {
			AuditLog::created( 'commission', $id, array( 'order_id' => $order_id, 'seller_id' => $seller_id, 'amount' => $amount ) );
		}

		return $id ?: new \WP_Error( 'insert_failed', __( 'خطا در ثبت پورسانت.', 'bespari-core' ) );
	}

	public function approve( int $id ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'پورسانت یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' !== $m->status ) {
			return new \WP_Error( 'invalid_status', __( 'فقط پورسانت در انتظار قابل تایید است.', 'bespari-core' ) );
		}
		$ok = $this->repo->update( $id, array( 'status' => 'approved', 'updated_at' => Helpers::now() ) );
		if ( $ok ) {
			AuditLog::log( 'approve', 'commission', $id, array( 'status' => 'pending' ), array( 'status' => 'approved' ) );
			do_action( 'bespari_commission_approved', $id, (int) $m->seller_id, (float) $m->amount );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در تایید پورسانت.', 'bespari-core' ) );
	}

	public function cancel( int $id ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'پورسانت یافت نشد.', 'bespari-core' ) );
		}
		if ( in_array( $m->status, array( 'paid', 'cancelled' ), true ) ) {
			return new \WP_Error( 'invalid_status', __( 'این پورسانت قابل لغو نیست.', 'bespari-core' ) );
		}
		$ok = $this->repo->update( $id, array( 'status' => 'cancelled', 'updated_at' => Helpers::now() ) );
		if ( $ok ) {
			AuditLog::log( 'cancel', 'commission', $id, array( 'status' => $m->status ), array( 'status' => 'cancelled' ) );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در لغو پورسانت.', 'bespari-core' ) );
	}

	/**
	 * هنگام cancel شدن سفارش، پورسانت را هم cancel کن.
	 */
	public function cancel_by_order( int $order_id ): void {
		$m = $this->repo->find_by_order( $order_id );
		if ( $m && ! in_array( $m->status, array( 'paid', 'cancelled' ), true ) ) {
			$this->repo->update( $m->id, array( 'status' => 'cancelled', 'updated_at' => Helpers::now() ) );
			AuditLog::log( 'cancel', 'commission', $m->id, array( 'status' => $m->status ), array( 'status' => 'cancelled' ) );
		}
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
