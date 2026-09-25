<?php
/**
 * بهینه‌سازی — کش آمار داشبورد + پاکسازی دوره‌ای.
 *
 * @package Bespari\Modules\Optimization
 */

namespace Bespari\Modules\Optimization;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Optimization {

	public function register(): void {
		// بازه روزانه سفارشی.
		add_filter( 'cron_schedules', array( $this, 'add_daily_schedule' ) );

		if ( ! wp_next_scheduled( 'bespari_daily_cleanup' ) ) {
			wp_schedule_event( time() + 3600, 'bespari_daily', 'bespari_daily_cleanup' );
		}

		add_action( 'bespari_daily_cleanup', array( $this, 'handle_cleanup' ) );

		// باطل کردن کش آمار پس از تغییر سفارش.
		add_action( 'bespari_order_created', array( $this, 'flush_stats_cache' ) );
		add_action( 'bespari_order_updated', array( $this, 'flush_stats_cache' ) );
		add_action( 'bespari_order_status_cancelled', array( $this, 'flush_stats_cache' ) );
	}

	/**
	 * بازه روزانه.
	 */
	public function add_daily_schedule( array $schedules ): array {
		if ( ! isset( $schedules['bespari_daily'] ) ) {
			$schedules['bespari_daily'] = array(
				'interval' => 86400,
				'display'  => __( 'روزانه (بسپاری)', 'bespari-core' ),
			);
		}
		return $schedules;
	}

	/**
	 * کلید کش آمار یک کاربر.
	 */
	public static function stats_key( int $user_id, string $scope ): string {
		return 'bespari_stats_' . $user_id . '_' . $scope;
	}

	/**
	 * خواندن آمار از کش (یا null).
	 *
	 * @return array|null
	 */
	public static function get_stats( int $user_id, string $scope ) {
		$cached = get_transient( self::stats_key( $user_id, $scope ) );
		return false === $cached ? null : $cached;
	}

	/**
	 * ذخیره آمار در کش (۱۵ دقیقه).
	 */
	public static function set_stats( int $user_id, string $scope, array $data ): void {
		set_transient( self::stats_key( $user_id, $scope ), $data, 900 );
	}

	/**
	 * باطل کردن کش آمار همه کاربران.
	 */
	public function flush_stats_cache(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient_bespari_stats_%' OR option_name LIKE '%transient_timeout_bespari_stats_%'" ); // phpcs:ignore
	}

	/**
	 * پاکسازی دوره‌ای: اعلان‌های قدیمی + trim لاگ‌ها + توکن‌های منقضی.
	 */
	public function handle_cleanup(): void {
		global $wpdb;

		// اعلان‌های قدیمی.
		( new \Bespari\Modules\Notification\NotificationService() )->prune( 60 );

		// trim لاگ ممیزی (نگه‌داشتن ۱۰۰۰ رکورد اخیر).
		$audit = Helpers::table( 'audit_log' );
		$wpdb->query( "DELETE FROM {$audit} WHERE id NOT IN ( SELECT id FROM ( SELECT id FROM {$audit} ORDER BY id DESC LIMIT 1000 ) AS keep )" ); // phpcs:ignore

		// trim لاگ اتصالات.
		$agent_log = Helpers::table( 'agent_log' );
		$wpdb->query( "DELETE FROM {$agent_log} WHERE id NOT IN ( SELECT id FROM ( SELECT id FROM {$agent_log} ORDER BY id DESC LIMIT 1000 ) AS keep )" ); // phpcs:ignore
	}
}
