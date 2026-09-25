<?php
/**
 * کلاس اصلی افزونه (Singleton) — ثبت هوک‌ها و راه‌اندازی ماژول‌ها.
 *
 * @package Bespari\Core
 */

namespace Bespari\Core;

use Bespari\Admin\Menu;
use Bespari\Admin\Assets;
use Bespari\Admin\Panel\AppPage;
use Bespari\Api\PosRestController;
use Bespari\Api\AuthRestController;
use Bespari\Api\PanelRestController;
use Bespari\Api\ProductionRestController;
use Bespari\Support\AuditLog;
use Bespari\Modules\Channel\Channel;
use Bespari\Modules\Brand\Brand;
use Bespari\Modules\Product\Product;
use Bespari\Modules\Pricing\PricingRule;
use Bespari\Modules\Order\Order;
use Bespari\Modules\Settlement\Settlement;
use Bespari\Modules\Seller\Seller;
use Bespari\Modules\Commission\Commission;
use Bespari\Modules\Payout\Payout;
use Bespari\Modules\Warehouse\Warehouse;
use Bespari\Modules\Shipping\Shipping;
use Bespari\Modules\Returns\Returns;
use Bespari\Modules\Request\Request;
use Bespari\Modules\Accounting\Accounting;
use Bespari\Modules\Report\Reports;
use Bespari\Modules\Agent\Agent;
use Bespari\Modules\Batch\Batch;
use Bespari\Modules\ChannelInvoice\ChannelInvoice;
use Bespari\Modules\Notification\Notification;
use Bespari\Modules\Optimization\Optimization;
use Bespari\Modules\Production\Production;
use Bespari\Modules\User\User;
use Bespari\Api\NotificationRestController;
use Bespari\Api\ErpRestController;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/**
	 * نمونه یکتا.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * لیست ماژول‌های ثبت‌شده.
	 *
	 * @var array
	 */
	private array $modules = array();

	/**
	 * دریافت نمونه یکتا.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor خصوصی (الگوی Singleton).
	 */
	private function __construct() {
		// ترجمه‌ها.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// هوک‌های ادمین.
		if ( is_admin() ) {
			( new Menu() )->register();
			( new Assets() )->register();
		}

		// اپ مستقل (/panel/) — rewrite فرانت + fallback admin-post.
		( new AppPage() )->register();

		// REST.
		( new PosRestController() )->register();
		( new AuthRestController() )->register();
		( new PanelRestController() )->register();
		( new NotificationRestController() )->register();
		( new ErpRestController() )->register();
		( new ProductionRestController() )->register();

		// لاگ تغییرات.
		( new AuditLog() )->register();

		// مهاجرت سبک حالت‌های تسویه قدیمی (self-healing).
		add_action( 'init', array( $this, 'maybe_migrate_settlement_modes' ), 1 );

		// مهاجرت ستون‌ها و نقش خط تولید (self-healing).
		add_action( 'init', array( $this, 'maybe_migrate_production' ), 1 );

		// همگام‌سازی cap مدیریت کاربران (self-healing).
		add_action( 'init', array( $this, 'maybe_migrate_users_cap' ), 1 );

		// راه‌اندازی ماژول‌ها.
		$this->boot_modules();
	}

	/**
	 * اگر کانالی حالت تسویه قدیمی دارد به «فاکتور به فاکتور» نگاشت می‌شود.
	 */
	public function maybe_migrate_settlement_modes(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'bespari_channels';

		$legacy = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE settlement_mode IN ( 'monthly', 'biweekly', 'weekly', 'custom' )" // phpcs:ignore
		);
		if ( $legacy > 0 ) {
			Activator::migrate_settlement_modes();
		}
	}

	/**
	 * مهاجرت ستون‌های خط تولید + همگام‌سازی نقش مدیر تولید.
	 */
	public function maybe_migrate_production(): void {
		Activator::maybe_add_production_columns();
		Activator::maybe_sync_production_role();
	}

	/**
	 * همگام‌سازی cap مدیریت کاربران.
	 */
	public function maybe_migrate_users_cap(): void {
		Activator::maybe_sync_users_cap();
	}

	/**
	 * اجرای افزونه (پس از bootstrap).
	 */
	public function run(): void {
		do_action( 'bespari_loaded', $this );
	}

	/**
	 * بارگذاری فایل ترجمه.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'bespari-core',
			false,
			dirname( plugin_basename( BESPARI_FILE ) ) . '/languages'
		);
	}

	/**
	 * راه‌اندازی ماژول‌های فاز ۱ تا ۴.
	 */
	private function boot_modules(): void {
		$modules = array(
			'channel'    => new Channel(),
			'brand'      => new Brand(),
			'product'    => new Product(),
			'pricing'    => new PricingRule(),
			'order'      => new Order(),
			'settlement' => new Settlement(),
			'seller'     => new Seller(),
			'commission' => new Commission(),
			'payout'     => new Payout(),
			'warehouse'  => new Warehouse(),
			'shipping'   => new Shipping(),
			'returns'    => new Returns(),
			'request'    => new Request(),
			'accounting' => new Accounting(),
			'reports'    => new Reports(),
			'agent'      => new Agent(),
			'batch'      => new Batch(),
			'channel_invoice' => new ChannelInvoice(),
			'notification'    => new Notification(),
			'optimization'    => new Optimization(),
			'production'      => new Production(),
			'user'            => new User(),
		);

		foreach ( $modules as $key => $module ) {
			if ( method_exists( $module, 'register' ) ) {
				$module->register();
			}
			$this->modules[ $key ] = $module;
		}

		do_action( 'bespari_modules_booted', $this->modules );
	}

	/**
	 * دریافت یک ماژول بر اساس کلید.
	 *
	 * @param string $key نام ماژول.
	 * @return object|null
	 */
	public function module( string $key ) {
		return $this->modules[ $key ] ?? null;
	}
}

/**
 * تابع کمکی برای دسترسی سریع به نمونه افزونه.
 *
 * @return Plugin
 */
function bespari(): Plugin {
	return Plugin::instance();
}
