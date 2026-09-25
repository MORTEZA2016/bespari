<?php
/**
 * Uninstall — حذف جداول و تنظیمات هنگام حذف افزونه از وردپرس.
 *
 * @package Bespari
 */

// جلوگیری از اجرای مستقیم.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$p = $wpdb->prefix . 'bespari_';

$tables = array(
	$p . 'audit_log',
	$p . 'notifications',
	$p . 'channel_invoices',
	$p . 'shipment_batches',
	$p . 'agent_log',
	$p . 'agents',
	$p . 'invoices',
	$p . 'transactions',
	$p . 'requests',
	$p . 'returns',
	$p . 'shipments',
	$p . 'stock_movements',
	$p . 'payouts',
	$p . 'commissions',
	$p . 'settlements',
	$p . 'order_items',
	$p . 'orders',
	$p . 'pricing_rules',
	$p . 'product_channel',
	$p . 'products',
	$p . 'brand_channels',
	$p . 'brands',
	$p . 'channel_settlement_periods',
	$p . 'channels',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
}

// تنظیمات و نسخه‌ها.
delete_option( 'bespari_settings' );
delete_option( 'bespari_db_version' );
delete_option( 'bespari_pricing_version' );

// حذف نقش‌های اختصاصی (نقش administrator دست‌نخورده می‌ماند).
remove_role( 'bespari_admin' );
remove_role( 'bespari_accountant' );
remove_role( 'bespari_warehouse' );
remove_role( 'bespari_seller' );
remove_role( 'bespari_viewer' );

// حذف قابلیت‌های بسپاری از نقش administrator (در صورت وجود).
$admin = get_role( 'administrator' );
if ( $admin ) {
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
		'bespari_seller_view',
	);
	foreach ( $caps as $cap ) {
		$admin->remove_cap( $cap );
	}
}

wp_clear_scheduled_hook( 'bespari_sync_orders' );
wp_clear_scheduled_hook( 'bespari_sync_prices' );
wp_clear_scheduled_hook( 'bespari_agent_check' );
