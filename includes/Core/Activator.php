<?php
/**
 * Activator — ایجاد جداول، نقش‌ها و داده‌های اولیه هنگام فعال‌سازی.
 *
 * @package Bespari\Core
 */

namespace Bespari\Core;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {

	/**
	 * اجرای عملیات فعال‌سازی.
	 */
	public static function activate(): void {
		self::create_tables();
		self::add_roles();
		self::register_capabilities();

		// ذخیره نسخه دیتابیس.
		update_option( 'bespari_db_version', BESPARI_VERSION );
		$existing = get_option( 'bespari_settings', array() );
		$defaults = array(
			'currency'                   => 'IRT',
			'tax_rate'                   => 9,
			'default_channel'            => 0,
			'audit_log_active'           => 1,
			'commission_base'            => 'net',
			'commission_default_percent' => 5,
			'stock_auto_deduct'          => 1,
			'agent_rate_limit'           => 60,
		);
		foreach ( $defaults as $k => $v ) {
			if ( ! array_key_exists( $k, $existing ) ) {
				$existing[ $k ] = $v;
			}
		}
		update_option( 'bespari_settings', $existing );

		// Cron سینک اقماری (فاز ۶) — idempotent.
		if ( ! wp_next_scheduled( 'bespari_agent_check' ) ) {
			wp_schedule_event( time() + 60, 'bespari_5min', 'bespari_agent_check' );
		}

		// سینک محصولات ووکامرس در اولین بار پس از فعال‌سازی — در یک cron تک‌بار،
		// چون ووکامرس هنگام register_activation_hook لزوماً بارگذاری نشده است.
		if ( ! wp_next_scheduled( 'bespari_initial_product_sync' ) ) {
			wp_schedule_single_event( time() + 30, 'bespari_initial_product_sync' );
		}

		do_action( 'bespari_activated' );
	}

	/**
	 * ایجاد جداول اختصاصی افزونه.
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'bespari_';

		// --- کانال‌های فروش -------------------------------------------------
		$sql_channels = "CREATE TABLE {$p}channels (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(100) NOT NULL,
			logo VARCHAR(255) DEFAULT '',
			status TINYINT(1) NOT NULL DEFAULT 1,
			is_marketplace TINYINT(1) NOT NULL DEFAULT 0,
			price_markup_percent DECIMAL(10,3) NOT NULL DEFAULT 0,
			credit_markup_percent DECIMAL(10,3) NOT NULL DEFAULT 0,
			commission_percent DECIMAL(10,3) NOT NULL DEFAULT 0,
			processing_fee_percent DECIMAL(10,3) NOT NULL DEFAULT 0,
			shipping_fee_percent DECIMAL(10,3) NOT NULL DEFAULT 0,
			advertising_fee_percent DECIMAL(10,3) NOT NULL DEFAULT 0,
			gateway_fee_percent DECIMAL(10,3) NOT NULL DEFAULT 0,
			fixed_fee DECIMAL(20,2) NOT NULL DEFAULT 0,
			tax_mode VARCHAR(20) NOT NULL DEFAULT 'inclusive',
			tax_percent DECIMAL(10,3) NOT NULL DEFAULT 0,
			settlement_mode VARCHAR(30) NOT NULL DEFAULT 'monthly',
			settlement_days TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			credit_limit DECIMAL(20,2) NOT NULL DEFAULT 0,
			api_settings LONGTEXT,
			webhook_url VARCHAR(255) DEFAULT '',
			shipping_window VARCHAR(100) NOT NULL DEFAULT '',
			production_priority TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			description TEXT,
			sort_order INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset};";

		// --- بازه‌های تسویه هر کانال ----------------------------------------
		$sql_periods = "CREATE TABLE {$p}channel_settlement_periods (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			channel_id BIGINT(20) UNSIGNED NOT NULL,
			title VARCHAR(100) NOT NULL,
			start_day TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
			end_day TINYINT(3) UNSIGNED NOT NULL DEFAULT 31,
			payment_due_days TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY channel_id (channel_id)
		) {$charset};";

		// --- برندها ----------------------------------------------------------
		$sql_brands = "CREATE TABLE {$p}brands (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(100) NOT NULL,
			logo VARCHAR(255) DEFAULT '',
			owner_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status TINYINT(1) NOT NULL DEFAULT 1,
			description TEXT,
			meta LONGTEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY owner_user_id (owner_user_id)
		) {$charset};";

		// --- نگاشت برند به کانال‌های فعال -----------------------------------
		$sql_brand_channels = "CREATE TABLE {$p}brand_channels (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			brand_id BIGINT(20) UNSIGNED NOT NULL,
			channel_id BIGINT(20) UNSIGNED NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY brand_channel (brand_id, channel_id)
		) {$charset};";

		// --- محصولات ---------------------------------------------------------
		$sql_products = "CREATE TABLE {$p}products (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			sku VARCHAR(100) NOT NULL DEFAULT '',
			product_code VARCHAR(100) NOT NULL DEFAULT '',
			wc_product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			brand_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			category_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			base_price DECIMAL(20,2) NOT NULL DEFAULT 0,
			purchase_price DECIMAL(20,2) NOT NULL DEFAULT 0,
			finished_cost DECIMAL(20,2) NOT NULL DEFAULT 0,
			stock INT(11) NOT NULL DEFAULT 0,
			low_stock_threshold INT(11) NOT NULL DEFAULT 5,
			status TINYINT(1) NOT NULL DEFAULT 1,
			description TEXT,
			meta LONGTEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY sku (sku),
			KEY wc_product_id (wc_product_id),
			KEY brand_id (brand_id),
			KEY category_id (category_id),
			KEY status (status)
		) {$charset};";

		// --- نگاشت محصول به کانال -------------------------------------------
		$sql_product_channels = "CREATE TABLE {$p}product_channel (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			channel_id BIGINT(20) UNSIGNED NOT NULL,
			variation_code VARCHAR(100) NOT NULL DEFAULT '',
			external_product_id VARCHAR(100) NOT NULL DEFAULT '',
			external_seller_id VARCHAR(100) NOT NULL DEFAULT '',
			product_url VARCHAR(255) NOT NULL DEFAULT '',
			channel_price DECIMAL(20,2) NOT NULL DEFAULT 0,
			channel_stock INT(11) NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			last_sync_at DATETIME NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY product_channel (product_id, channel_id),
			KEY channel_id (channel_id)
		) {$charset};";

		// --- قوانین قیمت‌گذاری ----------------------------------------------
		$sql_pricing_rules = "CREATE TABLE {$p}pricing_rules (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(191) NOT NULL,
			scope VARCHAR(30) NOT NULL DEFAULT 'channel',
			scope_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			channel_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			brand_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			category_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			seller_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			adjustment_type VARCHAR(20) NOT NULL DEFAULT 'percent',
			adjustment_value DECIMAL(20,3) NOT NULL DEFAULT 0,
			sale_type VARCHAR(20) NOT NULL DEFAULT 'all',
			priority INT(11) NOT NULL DEFAULT 10,
			stackable TINYINT(1) NOT NULL DEFAULT 1,
			valid_from DATE NULL,
			valid_to DATE NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			rule_version INT(11) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY scope (scope, scope_id),
			KEY channel_id (channel_id),
			KEY active_priority (is_active, priority)
		) {$charset};";

		// --- سفارشات ---------------------------------------------------------
		$sql_orders = "CREATE TABLE {$p}orders (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_number VARCHAR(50) NOT NULL,
			channel_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			brand_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			seller_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			customer_name VARCHAR(191) NOT NULL DEFAULT '',
			customer_phone VARCHAR(50) NOT NULL DEFAULT '',
			customer_address TEXT,
			sale_type VARCHAR(20) NOT NULL DEFAULT 'cash',
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
			settlement_status VARCHAR(30) NOT NULL DEFAULT 'pending',
			settlement_id BIGINT(20) UNSIGNED DEFAULT NULL,
			external_ref VARCHAR(64) NOT NULL DEFAULT '',
			batch_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			channel_invoice_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			production_priority INT(11) NOT NULL DEFAULT 0,
			production_date DATE NULL,
			production_moved TINYINT(1) NOT NULL DEFAULT 0,
			produced_at DATETIME NULL,
			ordered_at DATETIME NOT NULL,
			due_at DATETIME NULL,
			total_gross DECIMAL(20,2) NOT NULL DEFAULT 0,
			total_net DECIMAL(20,2) NOT NULL DEFAULT 0,
			total_profit DECIMAL(20,2) NOT NULL DEFAULT 0,
			rule_version INT(11) NOT NULL DEFAULT 1,
			pricing_snapshot LONGTEXT,
			financials_snapshot LONGTEXT,
			notes TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY order_number (order_number),
			KEY channel_status (channel_id, status),
			KEY seller_id (seller_id),
			KEY brand_id (brand_id),
			KEY settlement_id (settlement_id),
			KEY external_ref (external_ref),
			KEY batch_id (batch_id),
			KEY channel_invoice_id (channel_invoice_id),
			KEY ordered_at (ordered_at)
		) {$charset};";

		// --- آیتم‌های سفارش ---------------------------------------------------
		$sql_order_items = "CREATE TABLE {$p}order_items (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			product_name VARCHAR(255) NOT NULL DEFAULT '',
			sku VARCHAR(100) NOT NULL DEFAULT '',
			quantity INT(11) NOT NULL DEFAULT 1,
			base_price DECIMAL(20,2) NOT NULL DEFAULT 0,
			unit_price DECIMAL(20,2) NOT NULL DEFAULT 0,
			line_total DECIMAL(20,2) NOT NULL DEFAULT 0,
			cost DECIMAL(20,2) NOT NULL DEFAULT 0,
			financials LONGTEXT,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY order_product (order_id, product_id),
			KEY order_id (order_id),
			KEY product_id (product_id)
		) {$charset};";

		// --- تسویه‌ها ---------------------------------------------------------
		$sql_settlements = "CREATE TABLE {$p}settlements (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			channel_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			period_id BIGINT(20) UNSIGNED DEFAULT NULL,
			title VARCHAR(191) NOT NULL DEFAULT '',
			period_start DATE NOT NULL,
			period_end DATE NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			total_orders INT(11) NOT NULL DEFAULT 0,
			total_gross DECIMAL(20,2) NOT NULL DEFAULT 0,
			total_deductions DECIMAL(20,2) NOT NULL DEFAULT 0,
			total_net DECIMAL(20,2) NOT NULL DEFAULT 0,
			total_profit DECIMAL(20,2) NOT NULL DEFAULT 0,
			paid_at DATETIME NULL,
			notes TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY channel_id (channel_id),
			KEY status (status),
			KEY period (period_start, period_end)
		) {$charset};";

		// --- گردش موجودی انبار (فاز ۴) ---------------------------------------
		$sql_stock_movements = "CREATE TABLE {$p}stock_movements (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'in',
			quantity INT(11) NOT NULL DEFAULT 0,
			stock_before INT(11) NOT NULL DEFAULT 0,
			stock_after INT(11) NOT NULL DEFAULT 0,
			ref_type VARCHAR(30) NOT NULL DEFAULT '',
			ref_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			notes TEXT,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY product_created (product_id, created_at),
			KEY ref (ref_type, ref_id)
		) {$charset};";

		// --- برگه‌های ارسال (فاز ۴) -------------------------------------------
		$sql_shipments = "CREATE TABLE {$p}shipments (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			tracking_code VARCHAR(100) NOT NULL DEFAULT '',
			carrier VARCHAR(100) NOT NULL DEFAULT '',
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			shipped_at DATETIME NULL,
			delivered_at DATETIME NULL,
			notes TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY status (status)
		) {$charset};";

		// --- مرجوعی‌ها (فاز ۴) -------------------------------------------------
		$sql_returns = "CREATE TABLE {$p}returns (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			quantity INT(11) NOT NULL DEFAULT 1,
			reason VARCHAR(191) NOT NULL DEFAULT '',
			refund_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			restocked_at DATETIME NULL,
			notes TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY product_id (product_id),
			KEY status (status)
		) {$charset};";

		// --- درخواست‌های گردش کار (فاز ۴) ---------------------------------------
		$sql_requests = "CREATE TABLE {$p}requests (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(30) NOT NULL DEFAULT 'other',
			title VARCHAR(191) NOT NULL DEFAULT '',
			description TEXT,
			requester_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			assignee_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			resolved_at DATETIME NULL,
			resolution_notes TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY requester_status (requester_id, status),
			KEY status (status)
		) {$charset};";

		// --- تراکنش‌های کیف پول بازاریاب (فاز ۵) ---------------------------------
		$sql_transactions = "CREATE TABLE {$p}transactions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			seller_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			type VARCHAR(30) NOT NULL DEFAULT 'charge',
			amount DECIMAL(20,2) NOT NULL DEFAULT 0,
			balance_after DECIMAL(20,2) NOT NULL DEFAULT 0,
			ref_type VARCHAR(30) NOT NULL DEFAULT '',
			ref_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			description VARCHAR(255) NOT NULL DEFAULT '',
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY seller_created (seller_id, created_at),
			KEY ref (ref_type, ref_id)
		) {$charset};";

		// --- صورتحساب‌ها (فاز ۵) ----------------------------------------------
		$sql_invoices = "CREATE TABLE {$p}invoices (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_number VARCHAR(50) NOT NULL,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'sale',
			total DECIMAL(20,2) NOT NULL DEFAULT 0,
			meta LONGTEXT,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY invoice_number (invoice_number),
			KEY order_id (order_id)
		) {$charset};";

		// --- سایت‌های اقماری Agent Connector (فاز ۶) ----------------------------
		$sql_agents = "CREATE TABLE {$p}agents (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL DEFAULT '',
			site_url VARCHAR(255) NOT NULL DEFAULT '',
			api_key VARCHAR(64) NOT NULL DEFAULT '',
			api_secret VARCHAR(128) NOT NULL DEFAULT '',
			channel_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			seller_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			last_sync_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY api_key (api_key),
			KEY channel_id (channel_id),
			KEY status (status)
		) {$charset};";

		// --- لاگ اتصال‌های اقماری (فاز ۶) --------------------------------------
		$sql_agent_log = "CREATE TABLE {$p}agent_log (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			agent_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			direction VARCHAR(10) NOT NULL DEFAULT 'in',
			endpoint VARCHAR(100) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'ok',
			message TEXT,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY agent_created (agent_id, created_at)
		) {$charset};";

		// --- لاگ تغییرات -----------------------------------------------------
		$sql_audit = "CREATE TABLE {$p}audit_log (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(50) NOT NULL,
			entity_type VARCHAR(50) NOT NULL,
			entity_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			old_value LONGTEXT,
			new_value LONGTEXT,
			ip VARCHAR(45) DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY entity (entity_type, entity_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset};";

		// --- پورسانت بازاریاب (فاز ۳) ------------------------------------------
		$sql_commissions = "CREATE TABLE {$p}commissions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			seller_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			base_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
			base_type VARCHAR(20) NOT NULL DEFAULT 'net',
			percent DECIMAL(10,3) NOT NULL DEFAULT 0,
			amount DECIMAL(20,2) NOT NULL DEFAULT 0,
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			paid_at DATETIME NULL,
			payout_id BIGINT(20) UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY order_id (order_id),
			KEY seller_status (seller_id, status),
			KEY payout_id (payout_id)
		) {$charset};";

		// --- پرداخت‌ها / برداشت‌ها (فاز ۳) ----------------------------------
		$sql_payouts = "CREATE TABLE {$p}payouts (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			seller_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			amount DECIMAL(20,2) NOT NULL DEFAULT 0,
			method VARCHAR(30) NOT NULL DEFAULT 'manual',
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			requested_at DATETIME NOT NULL,
			paid_at DATETIME NULL,
			notes TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY seller_status (seller_id, status)
		) {$charset};";

		// --- محموله‌های کانالی (بازطراحی تسویه) ---------------------------------
		$sql_batches = "CREATE TABLE {$p}shipment_batches (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_code VARCHAR(50) NOT NULL,
			channel_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			settlement_mode VARCHAR(30) NOT NULL DEFAULT 'batch',
			status VARCHAR(30) NOT NULL DEFAULT 'pending',
			cash_tracking_code VARCHAR(100) NOT NULL DEFAULT '',
			cash_settled_at DATETIME NULL,
			credit_tracking_code VARCHAR(100) NOT NULL DEFAULT '',
			credit_settled_at DATETIME NULL,
			cash_total DECIMAL(20,2) NOT NULL DEFAULT 0,
			credit_total DECIMAL(20,2) NOT NULL DEFAULT 0,
			total_orders INT(11) NOT NULL DEFAULT 0,
			financials_snapshot LONGTEXT,
			shipped_at DATETIME NULL,
			channel_invoice_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			notes TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY batch_code (batch_code),
			KEY channel_status (channel_id, status),
			KEY channel_invoice_id (channel_invoice_id)
		) {$charset};";

		// --- صورت‌حساب‌های کانالی (بازطراحی تسویه) ------------------------------
		$sql_channel_invoices = "CREATE TABLE {$p}channel_invoices (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_number VARCHAR(50) NOT NULL,
			channel_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			period_days INT(11) NOT NULL DEFAULT 0,
			status VARCHAR(30) NOT NULL DEFAULT 'settling',
			cash_due_date DATE NULL,
			credit_due_date DATE NULL,
			cash_tracking_code VARCHAR(100) NOT NULL DEFAULT '',
			cash_settled_at DATETIME NULL,
			credit_tracking_code VARCHAR(100) NOT NULL DEFAULT '',
			credit_settled_at DATETIME NULL,
			cash_total DECIMAL(20,2) NOT NULL DEFAULT 0,
			credit_total DECIMAL(20,2) NOT NULL DEFAULT 0,
			total_batches INT(11) NOT NULL DEFAULT 0,
			financials_snapshot LONGTEXT,
			notes TEXT,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY invoice_number (invoice_number),
			KEY channel_status (channel_id, status)
		) {$charset};";

		$sql_notifications = "CREATE TABLE {$p}notifications (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) NOT NULL DEFAULT 0,
			type VARCHAR(40) NOT NULL DEFAULT 'info',
			title VARCHAR(255) NOT NULL,
			body TEXT,
			link VARCHAR(255) NOT NULL DEFAULT '',
			is_read TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_read (user_id, is_read),
			KEY user_created (user_id, created_at)
		) {$charset};";

		dbDelta( $sql_channels );
		dbDelta( $sql_periods );
		dbDelta( $sql_brands );
		dbDelta( $sql_brand_channels );
		dbDelta( $sql_products );
		dbDelta( $sql_product_channels );
		dbDelta( $sql_pricing_rules );
		dbDelta( $sql_orders );
		dbDelta( $sql_order_items );
		dbDelta( $sql_settlements );
		dbDelta( $sql_commissions );
		dbDelta( $sql_payouts );
		dbDelta( $sql_stock_movements );
		dbDelta( $sql_shipments );
		dbDelta( $sql_returns );
		dbDelta( $sql_requests );
		dbDelta( $sql_transactions );
		dbDelta( $sql_invoices );
		dbDelta( $sql_agents );
		dbDelta( $sql_agent_log );
		dbDelta( $sql_audit );
		dbDelta( $sql_batches );
		dbDelta( $sql_channel_invoices );
		dbDelta( $sql_notifications );

		// نگاشت یک‌باره حالت تسویه قدیمی → فاکتور به فاکتور (بازطراحی تسویه).
		self::migrate_settlement_modes();

		// درج کانال‌های پیش‌فرض (فقط اولین بار).
		self::seed_default_channels();
	}

	/**
	 * نگاشت یک‌باره حالت‌های تسویه قدیمی به «فاکتور به فاکتور».
	 */
	public static function migrate_settlement_modes(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'bespari_channels';

		$wpdb->query(
			"UPDATE {$table} SET settlement_mode = 'invoice'
			 WHERE settlement_mode IN ( 'monthly', 'biweekly', 'weekly', 'custom' )"
		);
	}

	/**
	 * افزودن ستون‌های خط تولید به نصب‌های موجود (self-healing).
	 *
	 * ستون‌ها از information_schema چک می‌شوند تا فقط ستون‌های ناقص ALTER شوند.
	 */
	public static function maybe_add_production_columns(): void {
		global $wpdb;

		$columns = array(
			$wpdb->prefix . 'bespari_channels' => array(
				'shipping_window'      => "ADD COLUMN shipping_window VARCHAR(100) NOT NULL DEFAULT ''",
				'production_priority'  => 'ADD COLUMN production_priority TINYINT(3) UNSIGNED NOT NULL DEFAULT 0',
			),
			$wpdb->prefix . 'bespari_orders'   => array(
				'production_priority'  => 'ADD COLUMN production_priority INT(11) NOT NULL DEFAULT 0',
				'production_date'      => 'ADD COLUMN production_date DATE NULL',
				'production_moved'     => 'ADD COLUMN production_moved TINYINT(1) NOT NULL DEFAULT 0',
				'produced_at'          => 'ADD COLUMN produced_at DATETIME NULL',
			),
		);

		foreach ( $columns as $table => $defs ) {
			$existing = array();
			$rows     = $wpdb->get_results( $wpdb->prepare(
				"SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
				$table
			) );
			if ( $rows ) {
				foreach ( $rows as $r ) {
					$existing[ strtolower( (string) $r->COLUMN_NAME ) ] = true;
				}
			}

			foreach ( $defs as $name => $sql ) {
				if ( isset( $existing[ $name ] ) ) {
					continue;
				}
				$wpdb->query( "ALTER TABLE {$table} {$sql}" ); // phpcs:ignore
			}
		}
	}

	/**
	 * اطمینان از وجود نقش مدیر تولید و cap آن روی نصب‌های موجود.
	 */
	public static function maybe_sync_production_role(): void {
		// نقش مدیر تولید.
		if ( null === get_role( 'bespari_production' ) ) {
			add_role( 'bespari_production', 'مدیر تولید بسپاری', array(
				'read'                    => true,
				'bespari_production_view' => true,
			) );
		}

		// cap به administrator.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'bespari_production_view' ) ) {
			$admin->add_cap( 'bespari_production_view' );
		}
	}

	/**
	 * اطمینان از وجود cap مدیریت کاربران روی نصب‌های موجود.
	 */
	public static function maybe_sync_users_cap(): void {
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'bespari_manage_users' ) ) {
			$admin->add_cap( 'bespari_manage_users' );
		}

		$erp_admin = get_role( 'bespari_admin' );
		if ( $erp_admin && ! $erp_admin->has_cap( 'bespari_manage_users' ) ) {
			$erp_admin->add_cap( 'bespari_manage_users' );
		}
	}

	/**
	 * درج کانال‌های پیش‌فرض در صورت خالی بودن جدول.
	 */
	private static function seed_default_channels(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'bespari_channels';

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return;
		}

		$defaults = array(
			array( 'دیجی‌کالا', 'digikala', 1 ),
			array( 'با سلام', 'basalam', 1 ),
			array( 'ترب', 'torob', 1 ),
			array( 'ایمالز', 'emalls', 1 ),
			array( 'اسنپ شاپ', 'snappshop', 1 ),
			array( 'دیوار', 'divar', 1 ),
			array( 'شیپور', 'sheypoor', 1 ),
			array( 'سایت اختصاصی', 'own-site', 0 ),
			array( 'فروش حضوری', 'in-person', 0 ),
			array( 'ثبت دستی', 'manual', 0 ),
		);

		$now = current_time( 'mysql' );
		foreach ( $defaults as $i => $row ) {
			$wpdb->insert(
				$table,
				array(
					'name'           => $row[0],
					'slug'           => $row[1],
					'status'         => 1,
					'is_marketplace' => $row[2],
					'created_at'     => $now,
					'updated_at'     => $now,
					'sort_order'     => $i,
				)
			);
		}
	}

	/**
	 * افزودن نقش‌های اختصاصی افزونه.
	 */
	private static function add_roles(): void {
		$base_caps = array(
			'read'                       => true,
			'bespari_read_dashboard'     => true,
		);

		// مدیر کل ERP.
		add_role( 'bespari_admin', 'مدیر بسپاری ERP', array_merge( $base_caps, array(
			'bespari_manage_channels'  => true,
			'bespari_manage_brands'    => true,
			'bespari_manage_products'  => true,
			'bespari_manage_pricing'   => true,
			'bespari_manage_orders'    => true,
			'bespari_manage_settlement' => true,
			'bespari_manage_finance'   => true,
			'bespari_manage_sellers'   => true,
			'bespari_manage_commission' => true,
			'bespari_manage_warehouse' => true,
			'bespari_manage_shipping'  => true,
			'bespari_manage_returns'   => true,
			'bespari_manage_requests'  => true,
			'bespari_view_reports'     => true,
			'bespari_manage_settings'  => true,
			'bespari_view_audit'       => true,
			'bespari_manage_users'     => true,
		) ) );

		// حسابدار.
		add_role( 'bespari_accountant', 'حسابدار بسپاری', array_merge( $base_caps, array(
			'bespari_manage_orders'     => true,
			'bespari_manage_settlement' => true,
			'bespari_manage_finance'    => true,
			'bespari_manage_commission' => true,
			'bespari_view_reports'      => true,
		) ) );

		// انباردار.
		add_role( 'bespari_warehouse', 'انباردار بسپاری', array_merge( $base_caps, array(
			'bespari_manage_warehouse' => true,
			'bespari_manage_shipping'  => true,
			'bespari_manage_returns'   => true,
		) ) );

		// بازاریاب / فروشنده.
		add_role( 'bespari_seller', 'بازاریاب بسپاری', array_merge( $base_caps, array(
			'bespari_seller_view'      => true,
			'bespari_manage_requests'  => true,
		) ) );

		// مدیر تولید (خط تولید).
		add_role( 'bespari_production', 'مدیر تولید بسپاری', array(
			'read'                       => true,
			'bespari_production_view'    => true,
		) );

		// بیننده (فقط گزارش).
		add_role( 'bespari_viewer', 'بیننده بسپاری', array_merge( $base_caps, array(
			'bespari_view_reports' => true,
		) ) );
	}

	/**
	 * افزودن قابلیت‌های لازم به نقش administrator.
	 */
	private static function register_capabilities(): void {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}

		$caps = array(
			'bespari_read_dashboard',
			'bespari_manage_channels',
			'bespari_manage_brands',
			'bespari_manage_products',
			'bespari_manage_pricing',
			'bespari_manage_orders',
			'bespari_manage_settlement',
			'bespari_manage_finance',
			'bespari_manage_sellers',
			'bespari_manage_commission',
			'bespari_manage_warehouse',
			'bespari_manage_shipping',
			'bespari_manage_returns',
			'bespari_manage_requests',
			'bespari_view_reports',
			'bespari_manage_settings',
			'bespari_view_audit',
			'bespari_production_view',
			'bespari_manage_users',
		);

		foreach ( $caps as $cap ) {
			$admin->add_cap( $cap );
		}
	}
}
