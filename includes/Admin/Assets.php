<?php
/**
 * بارگذاری فایل‌های استایل و اسکریپت ادمین (UI اختصاصی).
 *
 * @package Bespari\Admin
 */

namespace Bespari\Admin;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {

	/**
	 * ثبت هوک‌ها.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_head', array( $this, 'rtl_and_fonts' ) );
	}

	/**
	 * بارگذاری فقط در صفحات افزونه.
	 *
	 * @param string $hook هوک صفحه جاری.
	 */
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'bespari' ) ) {
			return;
		}

		// استایل اصلی.
		wp_enqueue_style(
			'bespari-admin',
			BESPARI_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			BESPARI_VERSION
		);

		// اسکریپت اصلی.
		wp_enqueue_script(
			'bespari-admin',
			BESPARI_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BESPARI_VERSION,
			true
		);

		wp_localize_script( 'bespari-admin', 'BespariAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'restUrl' => esc_url_raw( rest_url( 'bespari/v1/' ) ),
			'nonce'   => wp_create_nonce( 'bespari_ajax' ),
			'i18n'    => array(
				'confirmDelete' => __( 'آیا از حذف این مورد مطمئن هستید؟', 'bespari-core' ),
				'saved'         => __( 'ذخیره شد.', 'bespari-core' ),
				'error'         => __( 'خطایی رخ داد.', 'bespari-core' ),
				'cancel'        => __( 'انصراف', 'bespari-core' ),
			),
		) );

		// Chart.js برای داشبورد و گزارشات (CDN).
		if ( false !== strpos( $hook, 'bespari-erp' ) || false !== strpos( $hook, 'bespari-erp_page' ) || false !== strpos( $hook, 'bespari-reports' ) ) {
			wp_enqueue_script(
				'bespari-chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
				array(),
				'4.4.0',
				true
			);
		}

		// QR code برای برگه چاپی ارسال (فاز ۴).
		if ( false !== strpos( $hook, 'bespari-shipments' ) ) {
			wp_enqueue_script(
				'bespari-qrcode',
				'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js',
				array(),
				'1.0.0',
				true
			);
		}
	}

	/**
	 * تزریق استایل راست‌به‌چپ و فونت برای صفحات افزونه.
	 */
	public function rtl_and_fonts(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( $screen->id, 'bespari' ) ) {
			return;
		}
		?>
		<style>
			#wpbody-content { direction: rtl; }
			.bespari-wrap { font-family: "Vazirmatn", "IRANSans", Tahoma, sans-serif; }
		</style>
		<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
		<?php
	}
}
