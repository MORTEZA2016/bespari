<?php
/**
 * Plugin Name:       بسپاری Agent Connector
 * Description:       اتصال سایت بازاریاب به سایت مادر بسپاری ERP — ارسال خودکار سفارش‌ها و دریافت قیمت/موجودی (Push/Pull با HMAC).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Bespari
 * Author URI:        https://bespari.ir
 * License:           GPL-2.0-or-later
 * Text Domain:       bespari-agent
 *
 * @package BespariAgent
 */

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BESPARI_AGENT_VERSION', '1.0.0' );
define( 'BESPARI_AGENT_FILE', __FILE__ );
define( 'BESPARI_AGENT_PATH', plugin_dir_path( __FILE__ ) );

require_once BESPARI_AGENT_PATH . 'includes/Http.php';
require_once BESPARI_AGENT_PATH . 'includes/Admin.php';
require_once BESPARI_AGENT_PATH . 'includes/OrderSync.php';
require_once BESPARI_AGENT_PATH . 'includes/PriceSync.php';

register_activation_hook( __FILE__, array( 'BespariAgent\Admin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BespariAgent\Admin', 'deactivate' ) );

/**
 * بوت افزونه اقماری.
 */
function bespari_agent_boot(): void {
	BespariAgent\Admin::init();
	BespariAgent\OrderSync::init();
	BespariAgent\PriceSync::init();
}
add_action( 'plugins_loaded', 'bespari_agent_boot' );
