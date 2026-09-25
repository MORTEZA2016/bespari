<?php
/**
 * Deactivator — عملیات غیرفعال‌سازی (بدون حذف داده).
 *
 * @package Bespari\Core
 */

namespace Bespari\Core;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deactivator {

	/**
	 * اجرای عملیات غیرفعال‌سازی.
	 */
	public static function deactivate(): void {
		// پاک‌سازی cron های ثبت‌شده (در فازهای بعد با افزودن cron لازم می‌شود).
		wp_clear_scheduled_hook( 'bespari_sync_orders' );
		wp_clear_scheduled_hook( 'bespari_sync_prices' );

		do_action( 'bespari_deactivated' );
	}
}
