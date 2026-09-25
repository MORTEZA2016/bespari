<?php
/**
 * سرویس گزارشات (فقط خواندنی).
 *
 * @package Bespari\Modules\Report
 */

namespace Bespari\Modules\Report;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportService {

	/**
	 * فروش روزانه (SUM از orders، بدون سفارش لغوشده).
	 *
	 * @return array آرایه‌ای از stdClass: day, orders, net, profit
	 */
	public function daily_sales( string $date_from, string $date_to, int $channel_id = 0 ): array {
		global $wpdb;
		$orders = Helpers::table( 'orders' );

		$conditions = array( "status != 'cancelled'", 'ordered_at >= %s', 'ordered_at <= %s' );
		$values     = array( $date_from . ' 00:00:00', $date_to . ' 23:59:59' );

		if ( $channel_id > 0 ) {
			$conditions[] = 'channel_id = %d';
			$values[]     = $channel_id;
		}

		$where = implode( ' AND ', $conditions );

		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(ordered_at) AS day, COUNT(*) AS orders, SUM(total_net) AS net, SUM(total_profit) AS profit
			FROM {$orders} WHERE {$where}
			GROUP BY DATE(ordered_at) ORDER BY day ASC", // phpcs:ignore
			$values
		) );
	}

	/**
	 * جمع فروش per کانال.
	 *
	 * @return array آرایه‌ای از stdClass: channel_id, channel_name, orders, net, profit
	 */
	public function by_channel( string $date_from, string $date_to ): array {
		global $wpdb;
		$orders   = Helpers::table( 'orders' );
		$channels = Helpers::table( 'channels' );

		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT o.channel_id, COALESCE(c.name, '—') AS channel_name, COUNT(*) AS orders,
				COALESCE(SUM(o.total_net), 0) AS net, COALESCE(SUM(o.total_profit), 0) AS profit
			FROM {$orders} o LEFT JOIN {$channels} c ON c.id = o.channel_id
			WHERE o.status != 'cancelled' AND o.ordered_at >= %s AND o.ordered_at <= %s
			GROUP BY o.channel_id, c.name
			ORDER BY net DESC", // phpcs:ignore
			$date_from . ' 00:00:00',
			$date_to . ' 23:59:59'
		) );
	}

	/**
	 * پرفروش‌ترین محصولات (از order_items JOIN orders).
	 *
	 * @return array آرایه‌ای از stdClass: product_id, product_name, qty, total
	 */
	public function top_products( string $date_from, string $date_to, int $limit = 10 ): array {
		global $wpdb;
		$items  = Helpers::table( 'order_items' );
		$orders = Helpers::table( 'orders' );

		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT i.product_id, i.product_name, SUM(i.quantity) AS qty, SUM(i.line_total) AS total
			FROM {$items} i INNER JOIN {$orders} o ON o.id = i.order_id
			WHERE o.status != 'cancelled' AND o.ordered_at >= %s AND o.ordered_at <= %s
			GROUP BY i.product_id, i.product_name
			ORDER BY total DESC
			LIMIT %d", // phpcs:ignore
			$date_from . ' 00:00:00',
			$date_to . ' 23:59:59',
			max( 1, $limit )
		) );
	}

	/**
	 * سود و خسارت (P&L) — جمع از snapshot سفارش‌ها + کمیسیون/تسویه.
	 *
	 * @return array سطرهای P&L: label, value
	 */
	public function pnl( string $date_from, string $date_to, int $channel_id = 0 ): array {
		global $wpdb;
		$orders = Helpers::table( 'orders' );

		$conditions = array( "status != 'cancelled'", 'ordered_at >= %s', 'ordered_at <= %s' );
		$values     = array( $date_from . ' 00:00:00', $date_to . ' 23:59:59' );

		if ( $channel_id > 0 ) {
			$conditions[] = 'channel_id = %d';
			$values[]     = $channel_id;
		}

		$where = implode( ' AND ', $conditions );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_gross), 0) AS gross,
				COALESCE(SUM(total_net), 0) AS net, COALESCE(SUM(total_profit), 0) AS profit
			FROM {$orders} WHERE {$where}", // phpcs:ignore
			$values
		) );

		$gross  = (float) ( $row->gross ?? 0 );
		$net    = (float) ( $row->net ?? 0 );
		$profit = (float) ( $row->profit ?? 0 );

		// کمیسیون‌های تایید/پرداخت‌شده.
		$commissions_table = Helpers::table( 'commissions' );
		$comm_conditions   = array( "status IN ('approved','paid')", 'created_at >= %s', 'created_at <= %s' );
		$comm_values       = array( $date_from . ' 00:00:00', $date_to . ' 23:59:59' );
		if ( $channel_id > 0 ) {
			$orders_alias   = Helpers::table( 'orders' );
			$comm_conditions[] = "order_id IN (SELECT id FROM {$orders_alias} WHERE channel_id = %d)";
			$comm_values[]     = $channel_id;
		}
		$comm_where = implode( ' AND ', $comm_conditions );
		$commissions = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(amount), 0) FROM {$commissions_table} WHERE {$comm_where}", // phpcs:ignore
			$comm_values
		) );

		$gross_deductions = max( 0, $gross - $net );

		return array(
			array( 'label' => __( 'ناخالص فروش', 'bespari-core' ), 'value' => $gross ),
			array( 'label' => __( 'کسرهای کانال', 'bespari-core' ), 'value' => -1 * $gross_deductions ),
			array( 'label' => __( 'خالص فروش', 'bespari-core' ), 'value' => $net ),
			array( 'label' => __( 'بهای تمام‌شده', 'bespari-core' ), 'value' => -1 * max( 0, $net - $profit - $commissions ) ),
			array( 'label' => __( 'پورسانت بازاریاب‌ها', 'bespari-core' ), 'value' => -1 * $commissions ),
			array( 'label' => __( 'سود خالص', 'bespari-core' ), 'value' => $profit - $commissions ),
		);
	}

	/**
	 * کارت‌های KPI صفحه گزارشات.
	 */
	public function kpis(): array {
		global $wpdb;
		$orders = Helpers::table( 'orders' );
		$month_start = gmdate( 'Y-m-01' ) . ' 00:00:00';
		$now = Helpers::now();

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_net), 0) AS net, COALESCE(SUM(total_profit), 0) AS profit
			FROM {$orders} WHERE status != 'cancelled' AND ordered_at >= %s AND ordered_at <= %s", // phpcs:ignore
			$month_start,
			$now
		) );

		$txn_table = Helpers::table( 'transactions' );
		$wallet    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$txn_table}" ); // phpcs:ignore

		return array(
			'month_sales'  => (float) ( $row->net ?? 0 ),
			'month_profit' => (float) ( $row->profit ?? 0 ),
			'month_orders' => (int) ( $row->orders_count ?? 0 ),
			'wallet_total' => $wallet,
		);
	}
}
