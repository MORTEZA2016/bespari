<?php
/**
 * ماژول گزارشات — خروجی CSV.
 *
 * @package Bespari\Modules\Report
 */

namespace Bespari\Modules\Report;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reports {

	/**
	 * ثبت هوک‌ها.
	 */
	public function register(): void {
		add_action( 'admin_post_bespari_export_report', array( $this, 'handle_export_csv' ) );
	}

	/**
	 * خروجی CSV گزارش (فروش روزانه / top محصول).
	 */
	public function handle_export_csv(): void {
		if ( ! current_user_can( 'bespari_view_reports' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_export_report', 'bespari_nonce' );

		$report  = isset( $_GET['report'] ) ? sanitize_key( $_GET['report'] ) : 'daily';
		$from    = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : gmdate( 'Y-m-01' );
		$to      = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : gmdate( 'Y-m-d' );
		$channel = isset( $_GET['channel_id'] ) ? (int) $_GET['channel_id'] : 0;

		$service = new ReportService();

		if ( 'top' === $report ) {
			$rows = $service->top_products( $from, $to, 1000 );
			$head = array( __( 'شناسه محصول', 'bespari-core' ), __( 'نام محصول', 'bespari-core' ), __( 'تعداد', 'bespari-core' ), __( 'جمع فروش', 'bespari-core' ) );
			$name = 'top-products';
		} else {
			$rows = $service->daily_sales( $from, $to, $channel );
			$head = array( __( 'تاریخ', 'bespari-core' ), __( 'تعداد سفارش', 'bespari-core' ), __( 'خالص فروش', 'bespari-core' ), __( 'سود', 'bespari-core' ) );
			$name = 'daily-sales';
		}

		// هدر UTF-8 برای اکسل.
		$out = fopen( 'php://temp', 'r+' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $head );
		foreach ( $rows as $row ) {
			if ( 'top' === $report ) {
				fputcsv( $out, array( $row->product_id, $row->product_name, $row->qty, $row->total ) );
			} else {
				fputcsv( $out, array( $row->day, $row->orders, $row->net, $row->profit ) );
			}
		}

		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=bespari-' . $name . '-' . $from . '-' . $to . '.csv' );
		echo $csv; // phpcs:ignore
		exit;
	}
}
