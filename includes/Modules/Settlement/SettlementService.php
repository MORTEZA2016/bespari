<?php
/**
 * سرویس تسویه‌ها.
 *
 * @package Bespari\Modules\Settlement
 */

namespace Bespari\Modules\Settlement;

use Bespari\Modules\Order\OrderRepository;
use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettlementService {

	private SettlementRepository $repo;
	private OrderRepository $order_repo;

	public function __construct() {
		$this->repo       = new SettlementRepository();
		$this->order_repo = new OrderRepository();
	}

	public function list( array $args = array() ): array {
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters  = array_intersect_key( $args, array_flip( array( 'channel_id', 'status', 'date_from', 'date_to' ) ) );
		return $this->repo->paginate( $filters, $page, $per_page );
	}

	public function get( int $id ): ?SettlementModel {
		return $this->repo->get_by_id( $id );
	}

	/**
	 * ایجاد تسویه — جمع سفارش‌های pending بازه از financials_snapshot.
	 *
	 * @param int    $channel_id
	 * @param string $period_start  Y-m-d
	 * @param string $period_end    Y-m-d
	 * @param string $title
	 * @param ?int   $period_id
	 * @return int|\WP_Error
	 */
	public function generate( int $channel_id, string $period_start, string $period_end, string $title = '', ?int $period_id = null ) {
		if ( ! $channel_id || ! $period_start || ! $period_end ) {
			return new \WP_Error( 'missing_params', __( 'کانال و بازه تاریخ الزامی است.', 'bespari-core' ) );
		}

		$orders = $this->order_repo->get_unsettled_by_channel_period( $channel_id, $period_start, $period_end );
		if ( empty( $orders ) ) {
			return new \WP_Error( 'no_orders', __( 'سفارش تسویه‌نشده‌ای در این بازه یافت نشد.', 'bespari-core' ) );
		}

		$gross      = 0.0;
		$deductions = 0.0;
		$net        = 0.0;
		$profit     = 0.0;

		foreach ( $orders as $o ) {
			// همیشه از snapshot بخوان، نه PricingEngine زنده.
			$snap = is_array( $o->financials_snapshot ) ? $o->financials_snapshot : array();
			$gross      += (float) ( $snap['gross'] ?? $o->total_gross );
			$deductions += (float) ( $snap['deductions'] ?? 0 );
			$net        += (float) ( $snap['net'] ?? $o->total_net );
			$profit     += (float) ( $snap['profit'] ?? $o->total_profit );
		}

		if ( empty( $title ) ) {
			$title = sprintf( __( 'تسویه کانال %d — %s تا %s', 'bespari-core' ), $channel_id, $period_start, $period_end );
		}

		$now  = Helpers::now();
		$data = array(
			'channel_id'       => $channel_id,
			'period_id'        => $period_id,
			'title'            => sanitize_text_field( $title ),
			'period_start'     => $period_start,
			'period_end'       => $period_end,
			'status'           => 'pending',
			'total_orders'     => count( $orders ),
			'total_gross'      => $gross,
			'total_deductions' => $deductions,
			'total_net'        => $net,
			'total_profit'     => $profit,
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$settlement_id = $this->repo->insert( $data );
		if ( ! $settlement_id ) {
			return new \WP_Error( 'insert_failed', __( 'خطا در ایجاد تسویه.', 'bespari-core' ) );
		}

		global $wpdb;
		$orders_table = Helpers::table( 'orders' );
		foreach ( $orders as $o ) {
			$wpdb->update(
				$orders_table,
				array( 'settlement_id' => $settlement_id, 'settlement_status' => 'settled', 'updated_at' => $now ),
				array( 'id' => $o->id )
			);
		}

		AuditLog::created( 'settlement', $settlement_id, $data );
		return $settlement_id;
	}

	/**
	 * ثبت پرداخت تسویه.
	 *
	 * @param int $id آی‌دی تسویه.
	 * @return bool|\WP_Error
	 */
	public function mark_paid( int $id ) {
		$settlement = $this->repo->get_by_id( $id );
		if ( ! $settlement ) {
			return new \WP_Error( 'not_found', __( 'تسویه یافت نشد.', 'bespari-core' ) );
		}
		if ( 'paid' === $settlement->status ) {
			return new \WP_Error( 'already_paid', __( 'این تسویه قبلاً پرداخت شده است.', 'bespari-core' ) );
		}

		$now = Helpers::now();
		// به‌روزرسانی تسویه.
		$this->repo->update( $id, array( 'status' => 'paid', 'paid_at' => $now, 'updated_at' => $now ) );

		// به‌روزرسانی سفارش‌های این تسویه.
		global $wpdb;
		$orders_table = Helpers::table( 'orders' );
		$wpdb->update(
			$orders_table,
			array( 'settlement_status' => 'paid', 'updated_at' => $now ),
			array( 'settlement_id' => $id )
		);

		AuditLog::log( 'mark_paid', 'settlement', $id, array( 'status' => 'pending' ), array( 'status' => 'paid', 'paid_at' => $now ) );
		return true;
	}

	public function delete( int $id ): bool|\WP_Error {
		$existing = $this->repo->get_by_id( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'تسویه یافت نشد.', 'bespari-core' ) );
		}
		if ( 'paid' === $existing->status ) {
			return new \WP_Error( 'not_deletable', __( 'تسویه پرداخت‌شده قابل حذف نیست.', 'bespari-core' ) );
		}
		// برگرداندن وضعیت سفارش‌های این تسویه به pending.
		global $wpdb;
		$orders_table = Helpers::table( 'orders' );
		$wpdb->update(
			$orders_table,
			array( 'settlement_id' => null, 'settlement_status' => 'pending', 'updated_at' => Helpers::now() ),
			array( 'settlement_id' => $id )
		);

		$ok = $this->repo->delete( $id );
		if ( $ok ) {
			AuditLog::deleted( 'settlement', $id, $existing->to_array() );
		}
		return $ok ? true : new \WP_Error( 'delete_failed', __( 'خطا در حذف تسویه.', 'bespari-core' ) );
	}
}
