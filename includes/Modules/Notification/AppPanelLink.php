<?php
/**
 * لینک پنل مستقل برای کاربر.
 *
 * @package Bespari\Modules\Notification
 */

namespace Bespari\Modules\Notification;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AppPanelLink {

	/**
	 * لینک پنل مستقل (همه کاربران به یک URL می‌روند؛ توکن در سمت کلاینت است).
	 *
	 * @param int $user_id کاربر.
	 * @return string
	 */
	public static function for_seller( int $user_id ): string {
		return home_url( '/panel/' );
	}
}
