<?php
/**
 * منوی ادمین افزونه — تنها یک صفحه‌ی معرفی + لینک به پنل مستقل.
 *
 * @package Bespari\Admin
 */

namespace Bespari\Admin;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu {

	/**
	 * اسلاگ منوی اصلی.
	 */
	const PARENT_SLUG = 'bespari-erp';

	/**
	 * ثبت هوک‌ها.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
	}

	/**
	 * ثبت منو — فقط صفحه‌ی معرفی.
	 */
	public function register_menus(): void {
		$icon = 'dashicons-cart';

		add_menu_page(
			__( 'بسپاری ERP', 'bespari-core' ),
			__( 'بسپاری ERP', 'bespari-core' ),
			'bespari_read_dashboard',
			self::PARENT_SLUG,
			array( $this, 'render_intro' ),
			$icon,
			26
		);

		do_action( 'bespari_admin_menu', self::PARENT_SLUG );
	}

	/**
	 * صفحه‌ی معرفی + لینک به پنل مستقل.
	 */
	public function render_intro(): void {
		( new Pages\IntroPage() )->render();
	}
}
