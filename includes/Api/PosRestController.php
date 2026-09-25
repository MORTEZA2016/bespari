<?php
/**
 * REST پنل POS — جستجوی محصول، محاسبه زنده و ثبت سفارش.
 *
 * @package Bespari\Api
 */

namespace Bespari\Api;

use Bespari\Support\Helpers;
use Bespari\Modules\Product\ProductRepository;
use Bespari\Modules\Channel\ChannelRepository;
use Bespari\Modules\Order\OrderService;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PosRestController {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route( 'bespari/v1/pos', '/products', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_products' ),
			'permission_callback' => array( $this, 'can_use_pos' ),
		) );

		register_rest_route( 'bespari/v1/pos', '/calculate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_calculate' ),
			'permission_callback' => array( $this, 'can_use_pos' ),
		) );

		register_rest_route( 'bespari/v1/pos', '/order', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_order' ),
			'permission_callback' => array( $this, 'can_create_order' ),
		) );

		register_rest_route( 'bespari/v1/pos', '/channels', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_channels' ),
			'permission_callback' => array( $this, 'can_use_pos' ),
		) );
	}

	/**
	 * دسترسی خواندن POS — همه نقش‌های بسپاری (توکن مستقل یا کوکی).
	 */
	public function can_use_pos( $request ): bool {
		if ( ! AppAuth::authenticate( $request ) ) {
			return false;
		}
		return current_user_can( 'bespari_manage_orders' ) || current_user_can( 'bespari_seller_view' );
	}

	/**
	 * دسترسی ثبت سفارش.
	 */
	public function can_create_order( $request ): bool {
		if ( ! AppAuth::authenticate( $request ) ) {
			return false;
		}
		return current_user_can( 'bespari_manage_orders' ) || current_user_can( 'bespari_seller_view' );
	}

	/**
	 * GET products?s=جستجو — لیست محصولات برای جستجوی POS.
	 */
	public function handle_products( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$search = sanitize_text_field( (string) $request->get_param( 's' ) );
		$limit  = min( 50, max( 1, (int) $request->get_param( 'limit' ) ?: 25 ) );

		$repo  = new ProductRepository();
		$args  = array( 'per_page' => $limit, 'page' => 1, 'status' => 1 );
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$res  = $repo->paginate( $args );
		$out  = array();

		foreach ( (array) ( $res['items'] ?? array() ) as $p ) {
			$out[] = array(
				'id'         => (int) $p->id,
				'name'       => (string) $p->name,
				'sku'        => (string) $p->sku,
				'base_price' => Helpers::to_float( $p->base_price ),
				'stock'      => (int) $p->stock,
			);
		}

		return new \WP_REST_Response( array( 'ok' => true, 'products' => $out ) );
	}

	/**
	 * GET channels — کانال‌های فعال.
	 */
	public function handle_channels(): \WP_REST_Response {
		$repo    = new ChannelRepository();
		$channels = $repo->get_all( true );

		$out = array();
		foreach ( (array) $channels as $ch ) {
			$out[] = array(
				'id'              => (int) $ch->id,
				'name'            => (string) $ch->name,
				'settlement_mode' => (string) $ch->settlement_mode,
				'needs_customer'  => 'invoice' === $ch->settlement_mode,
			);
		}

		return new \WP_REST_Response( array( 'ok' => true, 'channels' => $out ) );
	}

	/**
	 * POST calculate — محاسبه زنده سبد با کانال انتخابی + قیمت دستی.
	 */
	public function handle_calculate( \WP_REST_Request $request ): \WP_REST_Response {
		$channel_id = (int) $request->get_param( 'channel_id' );
		$sale_type  = sanitize_key( (string) $request->get_param( 'sale_type' ) ?: 'cash' );
		$items      = $request->get_param( 'items' );

		if ( ! $channel_id || ! is_array( $items ) || empty( $items ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => __( 'کانال و حداقل یک محصول الزامی است.', 'bespari-core' ) ), 400 );
		}

		$clean_items = array();
		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean_items[] = array(
				'product_id'          => (int) ( $row['product_id'] ?? 0 ),
				'quantity'            => max( 1, (int) ( $row['quantity'] ?? 1 ) ),
				'unit_price_override' => Helpers::to_float( $row['unit_price_override'] ?? 0 ),
			);
		}

		if ( empty( $clean_items ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => __( 'حداقل یک محصول معتبر لازم است.', 'bespari-core' ) ), 400 );
		}

		$seller_id = current_user_can( 'bespari_seller_view' ) && ! current_user_can( 'bespari_manage_orders' )
			? get_current_user_id()
			: (int) $request->get_param( 'seller_id' );

		$calc   = new \Bespari\Modules\Finance\Calculator();
		$result = $calc->calculate_order( $channel_id, $sale_type, $seller_id, $clean_items );

		return new \WP_REST_Response( array(
			'ok'      => true,
			'gross'   => $result['gross'],
			'deductions' => $result['deductions'],
			'net'     => $result['net'],
			'profit'  => $result['profit'],
			'items'   => $result['items_breakdown'],
		) );
	}

	/**
	 * POST order — ثبت سفارش نهایی.
	 */
	public function handle_order( \WP_REST_Request $request ): \WP_REST_Response {
		$channel_id = (int) $request->get_param( 'channel_id' );
		$sale_type  = sanitize_key( (string) $request->get_param( 'sale_type' ) ?: 'cash' );
		$items      = $request->get_param( 'items' );

		if ( ! $channel_id || ! is_array( $items ) || empty( $items ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => __( 'کانال و حداقل یک محصول الزامی است.', 'bespari-core' ) ), 400 );
		}

		$clean_items = array();
		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean_items[] = array(
				'product_id'          => (int) ( $row['product_id'] ?? 0 ),
				'quantity'            => max( 1, (int) ( $row['quantity'] ?? 1 ) ),
				'unit_price_override' => Helpers::to_float( $row['unit_price_override'] ?? 0 ),
			);
		}

		// اطلاعات مشتری فقط برای کانال فاکتور به فاکتور الزامی است.
		$channel_repo = new ChannelRepository();
		$channel      = $channel_repo->get_by_id( $channel_id );
		$needs_customer = $channel && 'invoice' === $channel->settlement_mode;

		$customer_name    = sanitize_text_field( (string) $request->get_param( 'customer_name' ) );
		$customer_phone   = sanitize_text_field( (string) $request->get_param( 'customer_phone' ) );
		$customer_address = sanitize_textarea_field( (string) $request->get_param( 'customer_address' ) );

		if ( $needs_customer && ( '' === $customer_name || '' === $customer_phone ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => __( 'برای این کانال (فاکتور به فاکتور) نام و تلفن مشتری الزامی است.', 'bespari-core' ) ), 400 );
		}

		$service = new OrderService();
		$seller_id = current_user_can( 'bespari_seller_view' ) && ! current_user_can( 'bespari_manage_orders' )
			? get_current_user_id()
			: (int) $request->get_param( 'seller_id' );

		$order_id = $service->create( array(
			'channel_id'       => $channel_id,
			'seller_id'        => $seller_id,
			'sale_type'        => $sale_type,
			'items'            => $clean_items,
			'customer_name'    => $customer_name,
			'customer_phone'   => $customer_phone,
			'customer_address' => $customer_address,
			'notes'            => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
		) );

		if ( is_wp_error( $order_id ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => $order_id->get_error_message() ), 400 );
		}

		$repo    = new \Bespari\Modules\Order\OrderRepository();
		$order   = $repo->get_by_id( (int) $order_id );

		return new \WP_REST_Response( array(
			'ok'           => true,
			'order_id'     => (int) $order_id,
			'order_number' => $order ? (string) $order->order_number : '',
		) );
	}
}
