<?php
/**
 * ماژول Agent Connector — ثبت REST و Cron.
 *
 * @package Bespari\Modules\Agent
 */

namespace Bespari\Modules\Agent;

use Bespari\Api\AgentRestController;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Agent {

	/**
	 * ثبت هوک‌ها.
	 */
	public function register(): void {
		// REST endpoints.
		( new AgentRestController() )->register();

		// Cron schedules.
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		if ( ! wp_next_scheduled( 'bespari_agent_check' ) ) {
			wp_schedule_event( time() + 60, 'bespari_5min', 'bespari_agent_check' );
		}
		add_action( 'bespari_agent_check', array( $this, 'handle_cron_check' ) );

		// اکشن‌های ادمین.
		add_action( 'admin_post_bespari_save_agent', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_rotate_agent_secret', array( $this, 'handle_rotate_secret' ) );
		add_action( 'admin_post_bespari_delete_agent', array( $this, 'handle_delete' ) );
	}

	/**
	 * بازه‌های سفارشی cron.
	 */
	public function add_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['bespari_5min'] ) ) {
			$schedules['bespari_5min'] = array(
				'interval' => 300,
				'display'  => __( 'هر ۵ دقیقه (بسپاری)', 'bespari-core' ),
			);
		}
		if ( ! isset( $schedules['bespari_30min'] ) ) {
			$schedules['bespari_30min'] = array(
				'interval' => 1800,
				'display'  => __( 'هر ۳۰ دقیقه (بسپاری)', 'bespari-core' ),
			);
		}
		return $schedules;
	}

	/**
	 * کنترل دوره‌ای — trim لاگ (pull واقعی سمت اقماری است).
	 */
	public function handle_cron_check(): void {
		global $wpdb;

		$agents = Helpers::table( 'agents' );

		// مسدودسازی agentهای غیرفعال بیش از ۷ روز (اختیاری — فقط last_sync ثبت می‌شود).
		$wpdb->query( 'SELECT 1' ); // phpcs:ignore — نگه داشتن hook برای توسعه فازهای بعد.
	}

	/**
	 * ذخیره/ایجاد اتصال اقماری.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_settings' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_save_agent', 'bespari_nonce' );

		$raw     = isset( $_POST['agent'] ) && is_array( $_POST['agent'] ) ? wp_unslash( $_POST['agent'] ) : array(); // phpcs:ignore
		$agent_id = isset( $raw['id'] ) ? (int) $raw['id'] : 0;

		$service  = new AgentService();
		$redirect = add_query_arg( array( 'page' => 'bespari-agents' ), admin_url( 'admin.php' ) );

		if ( $agent_id > 0 ) {
			$result = $service->update( $agent_id, $raw );
			$redirect = add_query_arg( array( 'page' => 'bespari-agents', 'view' => $agent_id ), admin_url( 'admin.php' ) );
		} else {
			$result = $service->create( $raw );
		}

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), $redirect ) );
			exit;
		}

		if ( is_array( $result ) ) {
			// ایجاد موفق — با secret یک‌بارمصرف به صفحه جزئیات برو.
			$redirect = add_query_arg( array(
				'page'       => 'bespari-agents',
				'view'       => $result['agent_id'],
				'new_secret' => $result['api_secret'],
				'bespari_msg' => 'saved',
			), admin_url( 'admin.php' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'saved' ), $redirect ) );
		exit;
	}

	/**
	 * چرخش secret.
	 */
	public function handle_rotate_secret(): void {
		if ( ! current_user_can( 'bespari_manage_settings' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_rotate_agent_secret', 'bespari_nonce' );

		$id       = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$redirect = add_query_arg( array( 'page' => 'bespari-agents', 'view' => $id ), admin_url( 'admin.php' ) );

		$result = ( new AgentService() )->rotate_secret( $id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), $redirect ) );
			exit;
		}

		// secret جدید یک بار در URL نمایش داده می‌شود (پس از اولین بارگیری مخفی شود).
		wp_safe_redirect( add_query_arg( array( 'new_secret' => $result, 'bespari_msg' => 'saved' ), $redirect ) );
		exit;
	}

	/**
	 * حذف اتصال.
	 */
	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_settings' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}
		check_admin_referer( 'bespari_delete_agent', 'bespari_nonce' );

		$id       = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$redirect = add_query_arg( array( 'page' => 'bespari-agents' ), admin_url( 'admin.php' ) );

		$result = ( new AgentService() )->delete( $id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ), $redirect ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( array( 'bespari_msg' => 'saved' ), $redirect ) );
		exit;
	}
}
