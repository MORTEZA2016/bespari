<?php
/**
 * REST Controller اتصال‌های اقماری — HMAC + ۳ endpoint.
 *
 * @package Bespari\Api
 */

namespace Bespari\Api;

use Bespari\Modules\Agent\AgentService;
use Bespari\Modules\Order\OrderService;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgentRestController {

	/**
	 * ثبت REST routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * ثبت مسیرها.
	 */
	public function register_routes(): void {
		register_rest_route( 'bespari/v1', '/agent/handshake', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_handshake' ),
			'permission_callback' => '__return_true', // احراز هویت داخل handler برای پاس JSON یکنواخت.
		) );

		register_rest_route( 'bespari/v1', '/agent/orders', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_order' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'bespari/v1', '/agent/products', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_products' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * احراز هویت مشترک.
	 *
	 * @return array{agent: object, endpoint: string}|\WP_REST_Response پاسخ خطا یا آرایه در صورت موفقیت
	 */
	private function authenticate( string $endpoint ) {
		$service   = new AgentService();
		$api_key   = isset( $_SERVER['HTTP_X_BESPARI_KEY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_BESPARI_KEY'] ) ) : ''; // phpcs:ignore
		$timestamp = isset( $_SERVER['HTTP_X_BESPARI_TIMESTAMP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_BESPARI_TIMESTAMP'] ) ) : ''; // phpcs:ignore
		$signature = isset( $_SERVER['HTTP_X_BESPARI_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_BESPARI_SIGNATURE'] ) ) : ''; // phpcs:ignore

		if ( '' === $api_key || '' === $timestamp || '' === $signature ) {
			$this->log_anon( $endpoint, 'missing_headers' );
			return $this->error_response( 'auth_failed', __( 'احراز هویت نامعتبر است.', 'bespari-core' ), 401 );
		}

		// rate-limit.
		if ( ! $service->check_rate_limit( $api_key ) ) {
			$repo  = new \Bespari\Modules\Agent\AgentRepository();
			$agent = $repo->find_by_api_key( $api_key );
			$this->log_agent( $agent ? $agent->id : 0, $endpoint, 'rate_limited' );
			return $this->error_response( 'rate_limited', __( 'تعداد درخواست‌ها بیش از حد مجاز است.', 'bespari-core' ), 429 );
		}

		$raw_body = (string) file_get_contents( 'php://input' ); // phpcs:ignore

		$result = $service->verify_request( $api_key, $timestamp, $signature, $raw_body );
		if ( is_wp_error( $result ) ) {
			$this->log_anon( $endpoint, 'auth_failed' );
			return $this->error_response( $result->get_error_code(), $result->get_error_message(), 401 );
		}

		return array( 'agent' => $result, 'endpoint' => $endpoint );
	}

	/**
	 * پاسخ خطای JSON یکنواخت.
	 */
	private function error_response( string $code, string $message, int $status ): \WP_REST_Response {
		return new \WP_REST_Response( array( 'ok' => false, 'error' => $code, 'message' => $message ), $status );
	}

	/**
	 * لاگ بدون agent شناسایی‌شده.
	 */
	private function log_anon( string $endpoint, string $status ): void {
		global $wpdb;
		$wpdb->insert( Helpers::table( 'agent_log' ), array(
			'agent_id'   => 0,
			'direction'  => 'in',
			'endpoint'   => sanitize_text_field( $endpoint ),
			'status'     => sanitize_key( $status ),
			'message'    => '',
			'created_at' => Helpers::now(),
		) );
	}

	private function log_agent( int $agent_id, string $endpoint, string $status, string $message = '' ): void {
		( new AgentService() )->record_activity( $agent_id, 'in', $endpoint, $status, $message );
	}

	/**
	 * POST /agent/handshake — تست اتصال.
	 */
	public function handle_handshake(): \WP_REST_Response {
		$auth = $this->authenticate( 'handshake' );
		if ( $auth instanceof \WP_REST_Response ) {
			return $auth;
		}
		$agent = $auth['agent'];

		$this->log_agent( $agent->id, 'handshake', 'ok' );

		return new \WP_REST_Response( array(
			'ok'             => true,
			'site_name'      => get_bloginfo( 'name' ),
			'plugin_version' => BESPARI_VERSION,
		) );
	}

	/**
	 * POST /agent/orders — دریافت سفارش از سایت اقماری (idempotent با external_ref).
	 */
	public function handle_order(): \WP_REST_Response {
		$auth = $this->authenticate( 'orders' );
		if ( $auth instanceof \WP_REST_Response ) {
			return $auth;
		}
		$agent    = $auth['agent'];
		$service  = new AgentService();

		$raw_body = (string) file_get_contents( 'php://input' ); // phpcs:ignore
		$body     = json_decode( $raw_body, true );
		if ( ! is_array( $body ) ) {
			$this->log_agent( $agent->id, 'orders', 'error', 'invalid json' );
			return $this->error_response( 'invalid_body', __( 'بدنه درخواست معتبر نیست.', 'bespari-core' ), 400 );
		}

		$external_ref = isset( $body['external_ref'] ) ? sanitize_text_field( (string) $body['external_ref'] ) : '';
		if ( '' === $external_ref ) {
			$this->log_agent( $agent->id, 'orders', 'error', 'missing external_ref' );
			return $this->error_response( 'missing_ref', __( 'external_ref الزامی است.', 'bespari-core' ), 400 );
		}

		// Idempotency: سفارش همان external_ref + کانال agent قبلاً ثبت شده؟
		$orders   = Helpers::table( 'orders' );
		$existing = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare(
			"SELECT id FROM {$orders} WHERE external_ref = %s AND channel_id = %d LIMIT 1", // phpcs:ignore
			$external_ref,
			$agent->channel_id
		) );
		if ( $existing ) {
			$this->log_agent( $agent->id, 'orders', 'ok', 'duplicate -> idempotent #' . $existing );
			return new \WP_REST_Response( array(
				'ok'           => true,
				'order_id'     => (int) $existing,
				'idempotent'   => true,
			) );
		}

		// نگاشت آیتم‌ها با SKU/product_code.
		$items_input = isset( $body['items'] ) && is_array( $body['items'] ) ? $body['items'] : array();
		$items       = $this->map_items( $items_input );
		if ( is_wp_error( $items ) ) {
			$this->log_agent( $agent->id, 'orders', 'error', $items->get_error_message() );
			return $this->error_response( $items->get_error_code(), $items->get_error_message(), 400 );
		}

		$customer = isset( $body['customer'] ) && is_array( $body['customer'] ) ? $body['customer'] : array();

		$order_input = array(
			'channel_id'       => (int) $agent->channel_id,
			'seller_id'        => (int) $agent->seller_id,
			'customer_name'    => sanitize_text_field( $customer['name'] ?? '' ),
			'customer_phone'   => sanitize_text_field( $customer['phone'] ?? '' ),
			'customer_address' => sanitize_textarea_field( $customer['address'] ?? '' ),
			'sale_type'        => 'cash',
			'status'           => 'pending',
			'notes'            => sanitize_textarea_field( $body['notes'] ?? '' ),
			'items'            => $items,
		);

		$order_service = new OrderService();
		$order_id      = $order_service->create( $order_input );

		if ( is_wp_error( $order_id ) ) {
			$this->log_agent( $agent->id, 'orders', 'error', $order_id->get_error_message() );
			return $this->error_response( $order_id->get_error_code(), $order_id->get_error_message(), 400 );
		}

		// ذخیره external_ref.
		$GLOBALS['wpdb']->update( $orders, array( 'external_ref' => $external_ref ), array( 'id' => (int) $order_id ) );

		$order = $order_service->get( (int) $order_id );
		$this->log_agent( $agent->id, 'orders', 'ok', 'created #' . $order_id );

		return new \WP_REST_Response( array(
			'ok'           => true,
			'order_id'     => (int) $order_id,
			'order_number' => $order ? $order->order_number : '',
		) );
	}

	/**
	 * GET /agent/products — لیست محصولات فعال با قیمت کانال.
	 */
	public function handle_products(): \WP_REST_Response {
		$auth = $this->authenticate( 'products' );
		if ( $auth instanceof \WP_REST_Response ) {
			return $auth;
		}
		$agent = $auth['agent'];

		global $wpdb;
		$products = Helpers::table( 'products' );
		$pc       = Helpers::table( 'product_channel' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.sku, p.product_code, p.name, p.base_price, p.stock,
				pc.channel_price, pc.external_product_id
			FROM {$products} p
			LEFT JOIN {$pc} pc ON pc.product_id = p.id AND pc.channel_id = %d
			WHERE p.status = 1
			ORDER BY p.id ASC
			LIMIT 500", // phpcs:ignore
			$agent->channel_id
		) );

		$products_out = array();
		foreach ( (array) $rows as $row ) {
			$products_out[] = array(
				'sku'                 => (string) $row->sku,
				'product_code'        => (string) $row->product_code,
				'name'                => (string) $row->name,
				'base_price'          => (float) $row->base_price,
				'stock'               => (int) $row->stock,
				'channel_price'       => $row->channel_price ? (float) $row->channel_price : (float) $row->base_price,
				'external_product_id' => (string) $row->external_product_id,
			);
		}

		$this->log_agent( $agent->id, 'products', 'ok', count( $products_out ) . ' products' );

		return new \WP_REST_Response( array(
			'ok'          => true,
			'products'    => $products_out,
			'server_time' => Helpers::now(),
		) );
	}

	/**
	 * نگاشت آیتم‌های سفارش اقماری به محصولات HQ.
	 *
	 * @return array|\WP_Error آرایه [{product_id, quantity}] برای OrderService
	 */
	private function map_items( array $items_input ) {
		if ( empty( $items_input ) ) {
			return new \WP_Error( 'missing_items', __( 'حداقل یک محصول ارسال کنید.', 'bespari-core' ) );
		}

		global $wpdb;
		$products = Helpers::table( 'products' );

		$items = array();
		foreach ( $items_input as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$sku      = isset( $item['sku'] ) ? sanitize_text_field( (string) $item['sku'] ) : '';
			$code     = isset( $item['product_code'] ) ? sanitize_text_field( (string) $item['product_code'] ) : '';
			$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );

			if ( '' === $sku && '' === $code ) {
				continue;
			}

			$column = '' !== $code ? 'product_code' : 'sku';
			$value  = '' !== $code ? $code : $sku;

			$product_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$products} WHERE {$column} = %s AND status = 1 LIMIT 1", // phpcs:ignore
				$value
			) );

			if ( ! $product_id ) {
				return new \WP_Error( 'unknown_product', sprintf( /* translators: %s: sku */ __( 'محصول با شناسه %s یافت نشد.', 'bespari-core' ), $value ) );
			}

			$items[] = array( 'product_id' => (int) $product_id, 'quantity' => $quantity );
		}

		if ( empty( $items ) ) {
			return new \WP_Error( 'missing_items', __( 'حداقل یک محصول معتبر ارسال کنید.', 'bespari-core' ) );
		}

		return $items;
	}
}
