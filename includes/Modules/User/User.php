<?php
/**
 * ماژول کاربران — نقطه ورود ماژول.
 *
 * مدیریت کاربران دارای نقش bespari_* فقط از طریق REST (ErpRestController) انجام
 * می‌شود؛ این ماژول هوکی ثبت نمی‌کند.
 *
 * @package Bespari\Modules\User
 */

namespace Bespari\Modules\User;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class User {

	public function register(): void {
		// راه‌اندازی فعالی ندارد — همه‌ی عملیات‌ها از طریق REST است.
	}
}
