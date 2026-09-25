<?php
/**
 * Plugin Name:       بسپاری ERP
 * Plugin URI:        https://bespari.ir
 * Description:       سیستم جامع مدیریت فروش چندکاناله — سفارشات، قیمت‌گذاری، تسویه، پورسانت، انبار و گزارشات برای بازاریاب‌ها و برندها.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Bespari
 * Author URI:        https://bespari.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bespari-core
 * Domain Path:       /languages
 *
 * @package Bespari\Core
 */

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// نسخه فعلی افزونه.
define( 'BESPARI_VERSION', '1.0.0' );

// مسیر‌های اصلی.
define( 'BESPARI_FILE', __FILE__ );
define( 'BESPARI_PATH', plugin_dir_path( __FILE__ ) );
define( 'BESPARI_URL', plugin_dir_url( __FILE__ ) );

// Autoloader (PSR-4 با پیشوند Bespari\).
require_once BESPARI_PATH . 'includes/Core/Autoloader.php';
\Bespari\Core\Autoloader::register();

// فعال‌سازی / غیرفعال‌سازی.
register_activation_hook( __FILE__, array( '\Bespari\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\Bespari\Core\Deactivator', 'deactivate' ) );

// اجرای افزونه.
\Bespari\Core\Plugin::instance()->run();
