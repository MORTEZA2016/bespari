<?php
/**
 * ماژول کانال‌های فروش — ثبت هوک‌ها.
 *
 * @package Bespari\Modules\Channel
 */

namespace Bespari\Modules\Channel;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Channel {

	/**
	 * ثبت هوک‌ها.
	 */
	public function register(): void {
		add_action( 'admin_post_bespari_save_channel', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bespari_delete_channel', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_bespari_toggle_channel', array( $this, 'handle_toggle' ) );
	}

	/**
	 * ذخیره کانال (ایجاد / ویرایش).
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'bespari_manage_channels' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_save_channel', 'bespari_nonce' );

		$service = new ChannelService();
		$id      = isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0;

		$input = isset( $_POST['channel'] ) && is_array( $_POST['channel'] ) ? wp_unslash( $_POST['channel'] ) : array(); // phpcs:ignore

		if ( $id ) {
			$result = $service->update( $id, $input );
		} else {
			$result = $service->create( $input );
		}

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg(
				array( 'bespari_msg' => 'error', 'bespari_error' => urlencode( $result->get_error_message() ) ),
				wp_get_referer()
			) );
			exit;
		}

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-channels', 'bespari_msg' => 'saved' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * حذف کانال.
	 */
	public function handle_delete(): void {
		if ( ! current_user_can( 'bespari_manage_channels' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_delete_channel', 'bespari_nonce' );

		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new ChannelService();
		$service->delete( $id );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-channels', 'bespari_msg' => 'deleted' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * تغییر وضعیت فعال/غیرفعال.
	 */
	public function handle_toggle(): void {
		if ( ! current_user_can( 'bespari_manage_channels' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		check_admin_referer( 'bespari_toggle_channel', 'bespari_nonce' );

		$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$service = new ChannelService();
		$service->toggle_status( $id );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'bespari-channels', 'bespari_msg' => 'toggled' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}
}
