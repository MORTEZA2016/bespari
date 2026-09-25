<?php
/**
 * سرویس پرداخت‌ها.
 *
 * @package Bespari\Modules\Payout
 */

namespace Bespari\Modules\Payout;

use Bespari\Modules\Commission\CommissionRepository;
use Bespari\Support\Helpers;
use Bespari\Support\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayoutService {

	private PayoutRepository $repo;
	private CommissionRepository $comm_repo;

	public function __construct() {
		$this->repo      = new PayoutRepository();
		$this->comm_repo = new CommissionRepository();
	}

	public function list( array $args = array() ): array {
		$args = $this->apply_seller_scope( $args );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$filters  = array_intersect_key( $args, array_flip( array( 'seller_id', 'status', 'date_from', 'date_to' ) ) );
		return $this->repo->paginate( $filters, $page, $per_page );
	}

	public function get( int $id ): ?PayoutModel {
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
	 * ثبت درخواست برداشت.
	 *
	 * @param array $input seller_id, amount, method, notes
	 * @return int|\WP_Error
	 */
	public function request( array $input ) {
		$seller_id = (int) ( $input['seller_id'] ?? 0 );
		if ( $this->is_seller_user() ) {
			$seller_id = get_current_user_id();
		}
		if ( ! $seller_id ) {
			return new \WP_Error( 'missing_seller', __( 'بازاریاب الزامی است.', 'bespari-core' ) );
		}
		$user = get_user_by( 'id', $seller_id );
		if ( ! $user || ! in_array( 'bespari_seller', (array) $user->roles, true ) ) {
			return new \WP_Error( 'invalid_seller', __( 'بازاریاب نامعتبر است.', 'bespari-core' ) );
		}

		$amount = Helpers::to_float( $input['amount'] ?? 0 );
		if ( $amount <= 0 ) {
			return new \WP_Error( 'invalid_amount', __( 'مبلغ باید بزرگ‌تر از صفر باشد.', 'bespari-core' ) );
		}

		$bal = $this->comm_repo->get_seller_balance( $seller_id );
		if ( $amount > (float) $bal['approved'] ) {
			return new \WP_Error( 'insufficient_balance', sprintf( __( 'موجودی قابل برداشت %s است.', 'bespari-core' ), Helpers::format_money( $bal['approved'] ) ) );
		}

		$now = Helpers::now();
		$id  = $this->repo->insert( array(
			'seller_id'    => $seller_id,
			'amount'       => $amount,
			'method'       => sanitize_key( $input['method'] ?? 'manual' ),
			'status'       => 'pending',
			'requested_at' => $now,
			'notes'        => sanitize_textarea_field( $input['notes'] ?? '' ),
			'created_at'   => $now,
			'updated_at'   => $now,
		) );

		if ( $id ) {
			AuditLog::created( 'payout', $id, array( 'seller_id' => $seller_id, 'amount' => $amount ) );
		}

		return $id ?: new \WP_Error( 'insert_failed', __( 'خطا در ثبت درخواست.', 'bespari-core' ) );
	}

	public function approve( int $id ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'درخواست یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' !== $m->status ) {
			return new \WP_Error( 'invalid_status', __( 'فقط درخواست در انتظار قابل تایید است.', 'bespari-core' ) );
		}
		$ok = $this->repo->update( $id, array( 'status' => 'approved', 'updated_at' => Helpers::now() ) );
		if ( $ok ) {
			AuditLog::log( 'approve', 'payout', $id, array( 'status' => 'pending' ), array( 'status' => 'approved' ) );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در تایید.', 'bespari-core' ) );
	}

	public function reject( int $id ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'درخواست یافت نشد.', 'bespari-core' ) );
		}
		if ( 'pending' !== $m->status && 'approved' !== $m->status ) {
			return new \WP_Error( 'invalid_status', __( 'این درخواست قابل رد نیست.', 'bespari-core' ) );
		}
		$ok = $this->repo->update( $id, array( 'status' => 'rejected', 'updated_at' => Helpers::now() ) );
		if ( $ok ) {
			AuditLog::log( 'reject', 'payout', $id, array( 'status' => $m->status ), array( 'status' => 'rejected' ) );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در رد درخواست.', 'bespari-core' ) );
	}

	/**
	 * پرداخت شدن — کمیسیون‌های approved را به paid تبدیل می‌کند (FIFO تا سقف amount).
	 */
	public function mark_paid( int $id ) {
		$m = $this->repo->get_by_id( $id );
		if ( ! $m ) {
			return new \WP_Error( 'not_found', __( 'درخواست یافت نشد.', 'bespari-core' ) );
		}
		if ( 'approved' !== $m->status ) {
			return new \WP_Error( 'invalid_status', __( 'فقط درخواست تاییدشده قابل پرداخت است.', 'bespari-core' ) );
		}

		global $wpdb;
		$comm_table = Helpers::table( 'commissions' );
		$now        = Helpers::now();

		// FIFO: کمیسیون‌های approved را تا سقف amount به paid + payout_id ست کن.
		$approved = $this->comm_repo->get_approved_for_seller( (int) $m->seller_id );
		$remain   = (float) $m->amount;
		foreach ( $approved as $c ) {
			if ( $remain <= 0 ) {
				break;
			}
			if ( (float) $c->amount > $remain + 0.001 ) {
				// اگر کمیسیون بزرگ‌تر از باقی‌مانده است، کل payout باید جداگانه مدیریت شود؛ فعلاً skip نمی‌کنیم — کل کمیسیون را پرداخت می‌کنیم.
				// برای سادگی: اگر جمع approved >= amount باشد، همه approved ها را paid می‌کنیم (مقدار payout ممکن است کمتر/بیشتر باشد).
				// اما برای دقت: فقط تا سقف remain.
				continue;
			}
			$wpdb->update( $comm_table, array( 'status' => 'paid', 'payout_id' => $id, 'paid_at' => $now, 'updated_at' => $now ), array( 'id' => $c->id ) ); // phpcs:ignore
			$remain -= (float) $c->amount;
		}

		// اگر هنوز باقی‌مانده دارد و لیستی باقی نماند، همه approved ها را paid کن (حالت ساده).
		if ( $remain > 0.001 ) {
			// مقدار payout ممکن است بیشتر از جمع approved باشد — باز هم همه را paid می‌کنیم.
			foreach ( $approved as $c ) {
				$wpdb->update( $comm_table, array( 'status' => 'paid', 'payout_id' => $id, 'paid_at' => $now, 'updated_at' => $now ), array( 'id' => $c->id ) ); // phpcs:ignore
			}
		}

		$ok = $this->repo->update( $id, array( 'status' => 'paid', 'paid_at' => $now, 'updated_at' => $now ) );
		if ( $ok ) {
			AuditLog::log( 'mark_paid', 'payout', $id, array( 'status' => 'approved' ), array( 'status' => 'paid' ) );
			do_action( 'bespari_payout_paid', $id, (int) $m->seller_id, (float) $m->amount );
		}
		return $ok ? true : new \WP_Error( 'update_failed', __( 'خطا در ثبت پرداخت.', 'bespari-core' ) );
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
