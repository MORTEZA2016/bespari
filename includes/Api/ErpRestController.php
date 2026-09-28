<?php
/**
 * REST یکپارچه‌ی فیچرهای ERP — همه‌ی ماژول‌ها از اینجا به پنل مستقل سرو می‌شوند.
 *
 * الگو: PosRestController (نوشتن) + PanelRestController (خواندن).
 * خروجی view: {ok, title, stats?, headers, rows, pages, page, form?, rowActions?, filters?}
 *
 * @package Bespari\Api
 */

namespace Bespari\Api;

use Bespari\Modules\Brand\BrandService;
use Bespari\Modules\Channel\ChannelService;
use Bespari\Modules\Pricing\PricingRuleService;
use Bespari\Modules\Settlement\SettlementService;
use Bespari\Modules\Payout\PayoutService;
use Bespari\Modules\Shipping\ShipmentService;
use Bespari\Modules\Returns\ReturnService;
use Bespari\Modules\Request\RequestService;
use Bespari\Modules\Accounting\AccountingService;
use Bespari\Modules\Agent\AgentService;
use Bespari\Modules\User\UserService;
use Bespari\Modules\User\EmployeeService;
use Bespari\Modules\User\PermissionService;
use Bespari\Modules\Order\OrderRepository;
use Bespari\Modules\Seller\SellerRepository;
use Bespari\Modules\Commission\CommissionRepository;
use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ErpRestController {

	/**
	 * feature => handler method.
	 */
	private function features(): array {
		return array(
			'brands'          => 'brands',
			'channels'        => 'channels',
			'pricing'         => 'pricing',
			'settlements'     => 'settlements',
			'payouts'         => 'payouts',
			'shipments'       => 'shipments',
			'returns'         => 'returns',
			'requests'        => 'requests',
			'accounting'      => 'accounting',
			'invoices'        => 'invoices',
			'agents'          => 'agents',
			'settings'        => 'settings',
			'seller_dashboard'=> 'seller_dashboard',
			'users'           => 'users',
			'employees'       => 'employees',
		);
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$namespace = 'bespari/v1/erp';

		register_rest_route( $namespace, '/(?P<feature>[a-z_]+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_read' ),
				'permission_callback' => array( $this, 'can_access' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_write' ),
				'permission_callback' => array( $this, 'can_access' ),
			),
		) );

		register_rest_route( $namespace, '/(?P<feature>[a-z_]+)/(?P<id>\d+)', array(
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_write' ),
				'permission_callback' => array( $this, 'can_access' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'handle_delete' ),
				'permission_callback' => array( $this, 'can_access' ),
			),
		) );

		register_rest_route( $namespace, '/(?P<feature>[a-z_]+)/(?P<id>\d+)/(?P<action>[a-z_]+)', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_action' ),
			'permission_callback' => array( $this, 'can_access' ),
		) );

		register_rest_route( $namespace, '/(?P<feature>[a-z_]+)/preview', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_preview' ),
			'permission_callback' => array( $this, 'can_access' ),
		) );

		register_rest_route( $namespace, '/invoices/(?P<id>\d+)/print', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_invoice_print' ),
			'permission_callback' => function ( \WP_REST_Request $request ) {
				if ( ! AppAuth::authenticate( $request ) ) {
					return false;
				}
				return current_user_can( 'bespari_manage_finance' );
			},
		) );
	}

	/**
	 * احراز هویت + گارد دسترسی: caps منو + حالت دقیق PermissionService.
	 *
	 * GET و preview → نیاز به حالت read؛ سایر متدها (نوشتن/حذف/اکشن) → نیاز به edit.
	 */
	public function can_access( \WP_REST_Request $request ): bool {
		if ( ! AppAuth::authenticate( $request ) ) {
			return false;
		}

		$feature = (string) $request->get_param( 'feature' );
		$menus   = AppAuth::menus();

		if ( ! isset( $menus[ $feature ][1] ) ) {
			return false;
		}

		$route = (string) $request->get_route();
		$mode  = ( 'GET' === $request->get_method() || '/preview' === substr( $route, -strlen( '/preview' ) ) )
			? PermissionService::MODE_READ
			: PermissionService::MODE_EDIT;

		return PermissionService::can_menu( wp_get_current_user(), $feature, $mode );
	}

	/**
	 * آیا کاربر جاری seller-only است؟
	 */
	private function is_seller_only(): bool {
		return ! current_user_can( 'bespari_read_dashboard' );
	}

	/* ================= خواندن ================= */

	public function handle_read( \WP_REST_Request $request ): array {
		$feature = (string) $request->get_param( 'feature' );
		$method  = $this->features()[ $feature ] ?? '';

		if ( '' === $method || ! method_exists( $this, 'read_' . $method ) ) {
			return array( 'ok' => false, 'message' => __( 'فیچر نامعتبر است.', 'bespari-core' ) );
		}

		$args = array(
			's'        => (string) $request->get_param( 's' ),
			'status'   => (string) $request->get_param( 'status' ),
			'type'     => (string) $request->get_param( 'type' ),
			'role'     => (string) $request->get_param( 'role' ),
			'channel'  => (string) $request->get_param( 'channel' ),
			'paged'    => (int) $request->get_param( 'page' ),
			'page'     => (int) $request->get_param( 'page' ),
			'date_from'=> (string) $request->get_param( 'date_from' ),
			'date_to'  => (string) $request->get_param( 'date_to' ),
		);

		return call_user_func( array( $this, 'read_' . $method ), $args );
	}

	/* ================= نوشتن ================= */

	public function handle_write( \WP_REST_Request $request ): array {
		$feature = (string) $request->get_param( 'feature' );
		$id      = (int) $request->get_param( 'id' );
		$method  = $this->features()[ $feature ] ?? '';

		if ( '' === $method || ! method_exists( $this, 'write_' . $method ) ) {
			return array( 'ok' => false, 'message' => __( 'فیچر نامعتبر است.', 'bespari-core' ) );
		}

		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			$input = $request->get_params();
		}

		return call_user_func( array( $this, 'write_' . $method ), $id, $input );
	}

	public function handle_delete( \WP_REST_Request $request ): array {
		$feature = (string) $request->get_param( 'feature' );
		$id      = (int) $request->get_param( 'id' );
		$method  = $this->features()[ $feature ] ?? '';

		if ( '' === $method || ! method_exists( $this, 'delete_' . $method ) ) {
			return array( 'ok' => false, 'message' => __( 'فیچر نامعتبر است.', 'bespari-core' ) );
		}

		return call_user_func( array( $this, 'delete_' . $method ), $id );
	}

	public function handle_action( \WP_REST_Request $request ): array {
		$feature = (string) $request->get_param( 'feature' );
		$id      = (int) $request->get_param( 'id' );
		$action  = (string) $request->get_param( 'action' );
		$method  = $this->features()[ $feature ] ?? '';

		if ( '' === $method || ! method_exists( $this, 'action_' . $method ) ) {
			return array( 'ok' => false, 'message' => __( 'فیچر نامعتبر است.', 'bespari-core' ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		return call_user_func( array( $this, 'action_' . $method ), $id, $action, $params );
	}

	public function handle_preview( \WP_REST_Request $request ): array {
		$feature = (string) $request->get_param( 'feature' );

		if ( 'pricing' !== $feature ) {
			return array( 'ok' => false, 'message' => __( 'پیش‌نمایش فقط برای قوانین قیمت‌گذاری موجود است.', 'bespari-core' ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$product_id  = (int) ( $params['product_id'] ?? 0 );
		$channel_id  = (int) ( $params['channel_id'] ?? 0 );
		$sale_type   = sanitize_key( $params['sale_type'] ?? 'cash' );
		$quantity    = max( 1, (int) ( $params['quantity'] ?? 1 ) );

		if ( ! $product_id || ! $channel_id ) {
			return array( 'ok' => false, 'message' => __( 'محصول و کانال الزامی است.', 'bespari-core' ) );
		}

		$service = new PricingRuleService();
		$result  = $service->preview( $product_id, $channel_id, $sale_type, $quantity );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'preview' => $result );
	}

	/* ================= Brands ================= */

	private function read_brands( array $args ): array {
		$service = new BrandService();
		$brands  = $service->list();

		$rows = array();
		foreach ( $brands as $b ) {
			$rows[] = array( $b->id, esc_html( $b->name ), esc_html( $b->slug ), (int) $b->status );
		}

		return array(
			'ok'        => true,
			'title'     => __( 'برندهای فروش', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'نام', 'bespari-core' ), __( 'نامک', 'bespari-core' ), __( 'وضعیت', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => 1,
			'page'      => 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'name', 'label' => __( 'نام برند', 'bespari-core' ), 'type' => 'text', 'required' => true ),
					array( 'name' => 'status', 'label' => __( 'فعال', 'bespari-core' ), 'type' => 'checkbox' ),
					array( 'name' => 'logo', 'label' => __( 'لوگو (URL)', 'bespari-core' ), 'type' => 'text' ),
					array( 'name' => 'description', 'label' => __( 'توضیحات', 'bespari-core' ), 'type' => 'textarea' ),
				),
			),
			'rowActions' => array( 'delete' => __( 'حذف', 'bespari-core' ) ),
		);
	}

	private function write_brands( int $id, array $input ): array {
		$service = new BrandService();
		$result  = $id ? $service->update( $id, $input ) : $service->create( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function delete_brands( int $id ): array {
		$service = new BrandService();
		$result  = $service->delete( $id );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Channels ================= */

	private function read_channels( array $args ): array {
		global $wpdb;
		$service = new ChannelService();
		$channels = $service->list();

		$s = isset( $args['s'] ) ? trim( (string) $args['s'] ) : '';

		// محدودسازی لیست کانال‌ها بر اساس دسترسی دقیق کاربر جاری.
		$scope = PermissionService::allowed_ids( wp_get_current_user(), 'channels' );

		if ( ! PermissionService::can_view_channels( wp_get_current_user() ) ) {
			$channels = array();
		} elseif ( null !== $scope ) {
			$channels = array_filter(
				$channels,
				static function ( $ch ) use ( $scope ) {
					return in_array( (int) $ch->id, $scope, true );
				}
			);
		}

		// شمارش سفارش‌ها و برندهای مرتبط (یک کوئری برای هر کافی است).
		$orders = Helpers::table( 'orders' );
		$brand_channels = Helpers::table( 'brand_channels' );

		$order_counts = array();
		$order_rows   = $wpdb->get_results( "SELECT channel_id, COUNT(*) AS cnt FROM {$orders} GROUP BY channel_id" ); // phpcs:ignore
		if ( $order_rows ) {
			foreach ( $order_rows as $r ) {
				$order_counts[ (int) $r->channel_id ] = (int) $r->cnt;
			}
		}

		$brand_counts = array();
		$brand_rows   = $wpdb->get_results( "SELECT channel_id, COUNT(*) AS cnt FROM {$brand_channels} GROUP BY channel_id" ); // phpcs:ignore
		if ( $brand_rows ) {
			foreach ( $brand_rows as $r ) {
				$brand_counts[ (int) $r->channel_id ] = (int) $r->cnt;
			}
		}

		$rows       = array();
		$row_values = array();

		foreach ( $channels as $ch ) {
			if ( '' !== $s && false === stripos( $ch->name, $s ) ) {
				continue;
			}

			$status_badge = (int) $ch->status ? $this->badge_channel_status( 'active' ) : $this->badge_channel_status( 'inactive' );

			$rows[] = array(
				$ch->id,
				esc_html( $ch->name ),
				esc_html( $ch->settlement_label() ),
				esc_html( number_format_i18n( (float) $ch->commission_percent ) ) . '٪',
				number_format_i18n( $brand_counts[ $ch->id ] ?? 0 ),
				number_format_i18n( $order_counts[ $ch->id ] ?? 0 ),
				$status_badge,
			);

			$row_values[ $ch->id ] = array(
				'name'              => $ch->name,
				'settlement_mode'   => $ch->settlement_mode,
				'commission_percent'=> (float) $ch->commission_percent,
				'is_marketplace'    => (int) $ch->is_marketplace,
				'status'            => (int) $ch->status,
				'description'       => $ch->description,
			);
		}

		$settlement_options = array(
			array( 'value' => 'invoice',   'label' => __( 'فاکتور به فاکتور', 'bespari-core' ) ),
			array( 'value' => 'batch',     'label' => __( 'محموله به محموله', 'bespari-core' ) ),
			array( 'value' => 'statement', 'label' => __( 'صورت‌حساب به صورت‌حساب', 'bespari-core' ) ),
		);

		return array(
			'ok'        => true,
			'title'     => __( 'کانال‌های فروش', 'bespari-core' ),
			'headers'   => array(
				__( 'شناسه', 'bespari-core' ),
				__( 'نام کانال', 'bespari-core' ),
				__( 'حالت تسویه', 'bespari-core' ),
				__( 'پورسانت', 'bespari-core' ),
				__( 'برندها', 'bespari-core' ),
				__( 'سفارشات', 'bespari-core' ),
				__( 'وضعیت', 'bespari-core' ),
			),
			'rows'      => $rows,
			'rowValues' => $row_values,
			'pages'     => 1,
			'page'      => 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'name', 'label' => __( 'نام کانال', 'bespari-core' ), 'type' => 'text', 'required' => true ),
					array( 'name' => 'settlement_mode', 'label' => __( 'حالت تسویه', 'bespari-core' ), 'type' => 'select', 'options' => $settlement_options ),
					array( 'name' => 'commission_percent', 'label' => __( 'درصد پورسانت کانال (٪)', 'bespari-core' ), 'type' => 'number' ),
					array( 'name' => 'is_marketplace', 'label' => __( 'این کانال یک مارکت‌پلیس است', 'bespari-core' ), 'type' => 'checkbox' ),
					array( 'name' => 'status', 'label' => __( 'فعال', 'bespari-core' ), 'type' => 'checkbox' ),
					array( 'name' => 'description', 'label' => __( 'توضیحات', 'bespari-core' ), 'type' => 'textarea' ),
				),
			),
			'rowActions' => array(
				'edit'   => __( 'ویرایش', 'bespari-core' ),
				'delete' => __( 'حذف', 'bespari-core' ),
			),
		);
	}

	/**
	 * بج وضعیت کانال.
	 */
	private function badge_channel_status( string $status ): string {
		$map = array(
			'active'   => array( __( 'فعال', 'bespari-core' ), 'success' ),
			'inactive' => array( __( 'غیرفعال', 'bespari-core' ), 'muted' ),
		);
		$m   = $map[ $status ] ?? array( $status, 'muted' );
		return '<span class="bp-badge bp-badge--' . esc_attr( $m[1] ) . '">' . esc_html( $m[0] ) . '</span>';
	}

	private function write_channels( int $id, array $input ): array {
		$service = new ChannelService();
		$result  = $id ? $service->update( $id, $input ) : $service->create( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function delete_channels( int $id ): array {
		$service = new ChannelService();
		$result  = $service->delete( $id );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Pricing ================= */

	private function read_pricing( array $args ): array {
		$service = new PricingRuleService();
		$rules   = $service->list();

		$rows = array();
		foreach ( $rules as $r ) {
			$rows[] = array( $r->id, esc_html( $r->title ), esc_html( $r->scope ), esc_html( $r->adjustment_type ), esc_html( (string) $r->adjustment_value ), (int) $r->is_active );
		}

		return array(
			'ok'        => true,
			'title'     => __( 'قوانین قیمت‌گذاری', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'عنوان', 'bespari-core' ), __( 'محدوده', 'bespari-core' ), __( 'نوع تعدیل', 'bespari-core' ), __( 'مقدار', 'bespari-core' ), __( 'فعال', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => 1,
			'page'      => 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'title', 'label' => __( 'عنوان', 'bespari-core' ), 'type' => 'text', 'required' => true ),
					array( 'name' => 'scope', 'label' => __( 'محدوده', 'bespari-core' ), 'type' => 'text' ),
					array( 'name' => 'adjustment_type', 'label' => __( 'نوع تعدیل', 'bespari-core' ), 'type' => 'select', 'options' => array( 'percent', 'fixed' ) ),
					array( 'name' => 'adjustment_value', 'label' => __( 'مقدار تعدیل', 'bespari-core' ), 'type' => 'number' ),
					array( 'name' => 'priority', 'label' => __( 'اولویت', 'bespari-core' ), 'type' => 'number' ),
					array( 'name' => 'is_active', 'label' => __( 'فعال', 'bespari-core' ), 'type' => 'checkbox' ),
				),
			),
			'rowActions' => array( 'delete' => __( 'حذف', 'bespari-core' ) ),
			'preview'    => true,
		);
	}

	private function write_pricing( int $id, array $input ): array {
		$service = new PricingRuleService();
		$result  = $id ? $service->update( $id, $input ) : $service->create( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function delete_pricing( int $id ): array {
		$service = new PricingRuleService();
		$result  = $service->delete( $id );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Settlements ================= */

	private function read_settlements( array $args ): array {
		$service = new SettlementService();
		$args['per_page'] = 20;
		$result  = $service->list( $args );

		$rows = array();
		foreach ( $result['rows'] ?? array() as $s ) {
			$rows[] = array( $s->id, esc_html( $s->title ), esc_html( $s->status ), esc_html( (string) $s->total ), esc_html( (string) $s->period_start ) );
		}

		return array(
			'ok'        => true,
			'title'     => __( 'تسویه‌ها', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'عنوان', 'bespari-core' ), __( 'وضعیت', 'bespari-core' ), __( 'مبلغ', 'bespari-core' ), __( 'دوره', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => $result['pages'] ?? 1,
			'page'      => $result['page'] ?? 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'channel_id', 'label' => __( 'کانال', 'bespari-core' ), 'type' => 'number', 'required' => true ),
					array( 'name' => 'period_start', 'label' => __( 'شروع دوره', 'bespari-core' ), 'type' => 'date', 'required' => true ),
					array( 'name' => 'period_end', 'label' => __( 'پایان دوره', 'bespari-core' ), 'type' => 'date', 'required' => true ),
					array( 'name' => 'title', 'label' => __( 'عنوان', 'bespari-core' ), 'type' => 'text' ),
				),
			),
			'rowActions' => array(
				'mark_paid' => __( 'تسویه شد', 'bespari-core' ),
				'delete'    => __( 'حذف', 'bespari-core' ),
			),
		);
	}

	private function write_settlements( int $id, array $input ): array {
		$service = new SettlementService();

		$channel_id    = (int) ( $input['channel_id'] ?? 0 );
		$period_start  = sanitize_text_field( $input['period_start'] ?? '' );
		$period_end    = sanitize_text_field( $input['period_end'] ?? '' );
		$title         = sanitize_text_field( $input['title'] ?? '' );

		if ( ! $channel_id || '' === $period_start || '' === $period_end ) {
			return array( 'ok' => false, 'message' => __( 'کانال و بازه‌ی دوره الزامی است.', 'bespari-core' ) );
		}

		$result = $service->generate( $channel_id, $period_start, $period_end, $title );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function action_settlements( int $id, string $action, array $params ): array {
		$service = new SettlementService();

		if ( 'mark_paid' === $action ) {
			$result = $service->mark_paid( $id );
		} else {
			return array( 'ok' => false, 'message' => __( 'عملیات نامعتبر است.', 'bespari-core' ) );
		}

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	private function delete_settlements( int $id ): array {
		$service = new SettlementService();
		$result  = $service->delete( $id );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Payouts ================= */

	private function read_payouts( array $args ): array {
		$service = new PayoutService();

		if ( $this->is_seller_only() ) {
			$args['seller_id'] = get_current_user_id();
		}

		$args['per_page'] = 20;
		$result = $service->list( $args );

		$rows = array();
		foreach ( $result['rows'] ?? array() as $p ) {
			$rows[] = array( $p->id, esc_html( $p->seller_name ?? '' ), esc_html( (string) $p->amount ), esc_html( $p->status ), esc_html( (string) $p->method ) );
		}

		$row_actions = array();

		if ( ! $this->is_seller_only() ) {
			$row_actions['approve']   = __( 'تأیید', 'bespari-core' );
			$row_actions['reject']    = __( 'رد', 'bespari-core' );
			$row_actions['mark_paid'] = __( 'پرداخت شد', 'bespari-core' );
		}

		return array(
			'ok'        => true,
			'title'     => __( 'پرداخت‌ها', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'بازاریاب', 'bespari-core' ), __( 'مبلغ', 'bespari-core' ), __( 'وضعیت', 'bespari-core' ), __( 'روش', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => $result['pages'] ?? 1,
			'page'      => $result['page'] ?? 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'amount', 'label' => __( 'مبلغ', 'bespari-core' ), 'type' => 'number', 'required' => true ),
					array( 'name' => 'method', 'label' => __( 'روش', 'bespari-core' ), 'type' => 'select', 'options' => array( 'manual', 'bank' ) ),
					array( 'name' => 'notes', 'label' => __( 'یادداشت', 'bespari-core' ), 'type' => 'textarea' ),
				),
			),
			'rowActions' => $row_actions,
		);
	}

	private function write_payouts( int $id, array $input ): array {
		$service = new PayoutService();

		if ( $this->is_seller_only() ) {
			$input['seller_id'] = get_current_user_id();
		}

		$result = $service->request( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function action_payouts( int $id, string $action, array $params ): array {
		$service = new PayoutService();

		switch ( $action ) {
			case 'approve':
				$result = $service->approve( $id );
				break;
			case 'reject':
				$result = $service->reject( $id );
				break;
			case 'mark_paid':
				$result = $service->mark_paid( $id );
				break;
			default:
				return array( 'ok' => false, 'message' => __( 'عملیات نامعتبر است.', 'bespari-core' ) );
		}

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Shipments ================= */

	private function read_shipments( array $args ): array {
		$service = new ShipmentService();
		$args['per_page'] = 20;
		$result = $service->list( $args );

		$rows = array();
		foreach ( $result['rows'] ?? array() as $s ) {
			$rows[] = array( $s->id, esc_html( (string) $s->tracking_code ), esc_html( (string) $s->carrier ), esc_html( $s->status ), esc_html( (string) $s->order_id ) );
		}

		return array(
			'ok'        => true,
			'title'     => __( 'ارسال‌ها', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'کد رهگیری', 'bespari-core' ), __( 'حامل', 'bespari-core' ), __( 'وضعیت', 'bespari-core' ), __( 'سفارش', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => $result['pages'] ?? 1,
			'page'      => $result['page'] ?? 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'order_id', 'label' => __( 'سفارش', 'bespari-core' ), 'type' => 'number', 'required' => true ),
					array( 'name' => 'tracking_code', 'label' => __( 'کد رهگیری', 'bespari-core' ), 'type' => 'text' ),
					array( 'name' => 'carrier', 'label' => __( 'حامل', 'bespari-core' ), 'type' => 'text' ),
					array( 'name' => 'notes', 'label' => __( 'یادداشت', 'bespari-core' ), 'type' => 'textarea' ),
				),
			),
			'rowActions' => array(
				'ship'     => __( 'ارسال شد', 'bespari-core' ),
				'deliver'  => __( 'تحویل شد', 'bespari-core' ),
				'delete'   => __( 'حذف', 'bespari-core' ),
			),
		);
	}

	private function write_shipments( int $id, array $input ): array {
		$service = new ShipmentService();
		$result  = $service->save( $id, $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function action_shipments( int $id, string $action, array $params ): array {
		$service = new ShipmentService();

		$status_map = array(
			'ship'    => 'shipped',
			'deliver' => 'delivered',
		);

		if ( isset( $status_map[ $action ] ) ) {
			$result = $service->update_status( $id, $status_map[ $action ] );
		} else {
			return array( 'ok' => false, 'message' => __( 'عملیات نامعتبر است.', 'bespari-core' ) );
		}

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	private function delete_shipments( int $id ): array {
		$service = new ShipmentService();
		$result  = $service->delete( $id );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Returns ================= */

	private function read_returns( array $args ): array {
		$service = new ReturnService();
		$args['per_page'] = 20;
		$result = $service->list( $args );

		$rows = array();
		foreach ( $result['rows'] ?? array() as $r ) {
			$rows[] = array( $r->id, esc_html( (string) $r->order_id ), esc_html( $r->status ), esc_html( (string) $r->quantity ), esc_html( (string) $r->amount ) );
		}

		return array(
			'ok'        => true,
			'title'     => __( 'مرجوعی‌ها', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'سفارش', 'bespari-core' ), __( 'وضعیت', 'bespari-core' ), __( 'تعداد', 'bespari-core' ), __( 'مبلغ', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => $result['pages'] ?? 1,
			'page'      => $result['page'] ?? 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'order_id', 'label' => __( 'سفارش', 'bespari-core' ), 'type' => 'number', 'required' => true ),
					array( 'name' => 'product_id', 'label' => __( 'محصول', 'bespari-core' ), 'type' => 'number', 'required' => true ),
					array( 'name' => 'quantity', 'label' => __( 'تعداد', 'bespari-core' ), 'type' => 'number' ),
					array( 'name' => 'reason', 'label' => __( 'دلیل', 'bespari-core' ), 'type' => 'text' ),
					array( 'name' => 'notes', 'label' => __( 'یادداشت', 'bespari-core' ), 'type' => 'textarea' ),
				),
			),
			'rowActions' => array(
				'approve'  => __( 'تأیید', 'bespari-core' ),
				'reject'   => __( 'رد', 'bespari-core' ),
				'restock'  => __( 'بازگشت به انبار', 'bespari-core' ),
			),
		);
	}

	private function write_returns( int $id, array $input ): array {
		$service = new ReturnService();
		$result  = $service->save( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function action_returns( int $id, string $action, array $params ): array {
		$service = new ReturnService();

		switch ( $action ) {
			case 'approve':
				$result = $service->approve( $id );
				break;
			case 'reject':
				$result = $service->reject( $id );
				break;
			case 'restock':
				$result = $service->restock( $id );
				break;
			default:
				return array( 'ok' => false, 'message' => __( 'عملیات نامعتبر است.', 'bespari-core' ) );
		}

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Requests ================= */

	private function read_requests( array $args ): array {
		$service = new RequestService();

		if ( $this->is_seller_only() ) {
			$args['seller_id'] = get_current_user_id();
		}

		$args['per_page'] = 20;
		$result = $service->list( $args );

		$rows = array();
		foreach ( $result['rows'] ?? array() as $r ) {
			$rows[] = array( $r->id, esc_html( $r->type ), esc_html( $r->title ), esc_html( $r->status ), esc_html( (string) $r->created_at ) );
		}

		$row_actions = array( 'approve' => __( 'تأیید', 'bespari-core' ), 'reject' => __( 'رد', 'bespari-core' ) );

		return array(
			'ok'        => true,
			'title'     => __( 'درخواست‌ها', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'نوع', 'bespari-core' ), __( 'عنوان', 'bespari-core' ), __( 'وضعیت', 'bespari-core' ), __( 'تاریخ', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => $result['pages'] ?? 1,
			'page'      => $result['page'] ?? 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'type', 'label' => __( 'نوع', 'bespari-core' ), 'type' => 'select', 'options' => array( 'stock', 'price', 'discount', 'other' ) ),
					array( 'name' => 'title', 'label' => __( 'عنوان', 'bespari-core' ), 'type' => 'text', 'required' => true ),
					array( 'name' => 'description', 'label' => __( 'توضیحات', 'bespari-core' ), 'type' => 'textarea' ),
					array( 'name' => 'product_id', 'label' => __( 'محصول', 'bespari-core' ), 'type' => 'number' ),
				),
			),
			'rowActions' => $row_actions,
		);
	}

	private function write_requests( int $id, array $input ): array {
		$service = new RequestService();

		if ( $this->is_seller_only() ) {
			$input['seller_id'] = get_current_user_id();
		}

		$result = $service->save( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function action_requests( int $id, string $action, array $params ): array {
		$service = new RequestService();
		$notes   = sanitize_text_field( $params['notes'] ?? '' );

		if ( 'approve' === $action ) {
			$result = $service->approve( $id, $notes );
		} elseif ( 'reject' === $action ) {
			$result = $service->reject( $id, $notes );
		} else {
			return array( 'ok' => false, 'message' => __( 'عملیات نامعتبر است.', 'bespari-core' ) );
		}

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Accounting ================= */

	private function read_accounting( array $args ): array {
		$service = new AccountingService();

		if ( $this->is_seller_only() ) {
			$args['seller_id'] = get_current_user_id();
		}

		$args['per_page'] = 20;
		$result = $service->list_transactions( $args );

		$rows = array();
		foreach ( $result['rows'] ?? array() as $t ) {
			$rows[] = array( $t->id, esc_html( $t->type ), esc_html( (string) $t->amount ), esc_html( $t->description ), esc_html( (string) $t->created_at ) );
		}

		$stats = array();
		if ( ! $this->is_seller_only() ) {
			$balances = $service->get_all_seller_balances();

			foreach ( $balances as $b ) {
				$stats[] = array(
					'label' => $b['name'] ?? '',
					'value' => Helpers::format_money( (float) ( $b['balance'] ?? 0 ) ),
				);
			}
		}

		return array(
			'ok'        => true,
			'title'     => __( 'حسابداری', 'bespari-core' ),
			'stats'     => $stats,
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'نوع', 'bespari-core' ), __( 'مبلغ', 'bespari-core' ), __( 'توضیحات', 'bespari-core' ), __( 'تاریخ', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => $result['pages'] ?? 1,
			'page'      => $result['page'] ?? 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'seller_id', 'label' => __( 'بازاریاب', 'bespari-core' ), 'type' => 'number', 'required' => true ),
					array( 'name' => 'type', 'label' => __( 'نوع', 'bespari-core' ), 'type' => 'select', 'options' => array( 'charge', 'adjust', 'refund' ) ),
					array( 'name' => 'amount', 'label' => __( 'مبلغ', 'bespari-core' ), 'type' => 'number', 'required' => true ),
					array( 'name' => 'description', 'label' => __( 'توضیحات', 'bespari-core' ), 'type' => 'textarea' ),
				),
			),
		);
	}

	private function write_accounting( int $id, array $input ): array {
		$service     = new AccountingService();
		$seller_id   = (int) ( $input['seller_id'] ?? 0 );
		$type        = sanitize_key( $input['type'] ?? 'adjust' );
		$amount      = (float) ( $input['amount'] ?? 0 );
		$description = sanitize_text_field( $input['description'] ?? '' );

		if ( ! $seller_id || ! $amount ) {
			return array( 'ok' => false, 'message' => __( 'بازاریاب و مبلغ الزامی است.', 'bespari-core' ) );
		}

		$result = $service->record( $seller_id, $type, $amount, 'manual', 0, $description );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	/* ================= Invoices ================= */

	private function read_invoices( array $args ): array {
		$service = new AccountingService();
		$args['per_page'] = 20;
		$result = $service->list_invoices( $args );

		$rows = array();
		foreach ( $result['rows'] ?? array() as $inv ) {
			$rows[] = array( $inv->id, esc_html( (string) $inv->invoice_number ), esc_html( (string) $inv->total ), esc_html( (string) $inv->created_at ) );
		}

		return array(
			'ok'        => true,
			'title'     => __( 'صورتحساب‌ها', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'شماره', 'bespari-core' ), __( 'مبلغ', 'bespari-core' ), __( 'تاریخ', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => $result['pages'] ?? 1,
			'page'      => $result['page'] ?? 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'order_id', 'label' => __( 'سفارش', 'bespari-core' ), 'type' => 'number', 'required' => true ),
				),
			),
			'rowActions' => array( 'print' => __( 'چاپ', 'bespari-core' ) ),
		);
	}

	private function write_invoices( int $id, array $input ): array {
		$service  = new AccountingService();
		$order_id = (int) ( $input['order_id'] ?? 0 );

		if ( ! $order_id ) {
			return array( 'ok' => false, 'message' => __( 'سفارش الزامی است.', 'bespari-core' ) );
		}

		$result = $service->generate_invoice( $order_id );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	/* ================= Agents ================= */

	private function read_agents( array $args ): array {
		$service = new AgentService();
		$agents  = $service->list();

		$rows = array();
		foreach ( $agents as $a ) {
			$rows[] = array( $a->id, esc_html( $a->name ), esc_html( (string) $a->api_key ), esc_html( (string) $a->status ) );
		}

		return array(
			'ok'        => true,
			'title'     => __( 'اتصال‌ها', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'نام', 'bespari-core' ), __( 'کلید API', 'bespari-core' ), __( 'وضعیت', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => 1,
			'page'      => 1,
			'form'      => array(
				'fields' => array(
					array( 'name' => 'name', 'label' => __( 'نام', 'bespari-core' ), 'type' => 'text', 'required' => true ),
					array( 'name' => 'site_url', 'label' => __( 'آدرس سایت', 'bespari-core' ), 'type' => 'text' ),
				),
			),
			'rowActions' => array(
				'rotate_secret' => __( 'کلید جدید', 'bespari-core' ),
				'delete'        => __( 'حذف', 'bespari-core' ),
			),
		);
	}

	private function write_agents( int $id, array $input ): array {
		$service = new AgentService();
		$result  = $id ? $service->update( $id, $input ) : $service->create( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		// در create، راز یک‌بار برگردانده می‌شود.
		if ( is_array( $result ) && isset( $result['api_secret'] ) ) {
			return array( 'ok' => true, 'id' => $result['agent_id'] ?? 0, 'api_secret' => $result['api_secret'] );
		}

		return array( 'ok' => true, 'id' => is_array( $result ) ? ( $result['agent_id'] ?? 0 ) : $result );
	}

	private function action_agents( int $id, string $action, array $params ): array {
		$service = new AgentService();

		if ( 'rotate_secret' === $action ) {
			$result = $service->rotate_secret( $id );

			if ( is_wp_error( $result ) ) {
				return array( 'ok' => false, 'message' => $result->get_error_message() );
			}

			return array( 'ok' => true, 'api_secret' => $result['api_secret'] ?? '' );
		}

		return array( 'ok' => false, 'message' => __( 'عملیات نامعتبر است.', 'bespari-core' ) );
	}

	private function delete_agents( int $id ): array {
		$service = new AgentService();
		$result  = $service->delete( $id );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Settings ================= */

	private function read_settings( array $args ): array {
		$settings = get_option( 'bespari_settings', array() );

		return array(
			'ok'    => true,
			'title' => __( 'تنظیمات', 'bespari-core' ),
			'settings' => array(
				'currency'         => $settings['currency'] ?? 'IRT',
				'tax_rate'         => (float) ( $settings['tax_rate'] ?? 0 ),
				'audit_log_active' => (bool) ( $settings['audit_log_active'] ?? true ),
			),
			'system' => array(
				'version'      => BESPARI_VERSION,
				'db_version'   => get_option( 'bespari_db_version' ),
				'pricing_version' => get_option( 'bespari_pricing_version' ),
				'woocommerce'  => Helpers::woocommerce_is_active(),
			),
		);
	}

	private function write_settings( int $id, array $input ): array {
		$settings = get_option( 'bespari_settings', array() );

		if ( isset( $input['currency'] ) ) {
			$settings['currency'] = sanitize_key( $input['currency'] );
		}
		if ( isset( $input['tax_rate'] ) ) {
			$settings['tax_rate'] = (float) $input['tax_rate'];
		}
		if ( isset( $input['audit_log_active'] ) ) {
			$settings['audit_log_active'] = (bool) $input['audit_log_active'];
		}

		update_option( 'bespari_settings', $settings );

		return array( 'ok' => true );
	}

	public function handle_invoice_print( \WP_REST_Request $request ): array {
		$id      = (int) $request->get_param( 'id' );
		$service = new AccountingService();
		$invoice = $service->get_invoice( $id );

		if ( ! $invoice ) {
			return array( 'ok' => false, 'message' => __( 'صورتحساب یافت نشد.', 'bespari-core' ) );
		}

		$lines = array();
		foreach ( $invoice->lines() as $line ) {
			$lines[] = array(
				'name'     => esc_html( (string) $line->product_name ),
				'qty'      => (int) $line->quantity,
				'price'    => esc_html( Helpers::format_money( (float) $line->unit_price ) ),
				'total'    => esc_html( Helpers::format_money( (float) $line->total ) ),
			);
		}

		$html  = '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">';
		$html .= '<title>صورتحساب ' . esc_html( (string) $invoice->invoice_number ) . '</title>';
		$html .= '<style>body{font-family:Tahoma,sans-serif;padding:30px;color:#222}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:8px;text-align:right}h1{font-size:20px}.muted{color:#666;font-size:12px}</style>';
		$html .= '</head><body>';
		$html .= '<h1>صورتحساب ' . esc_html( (string) $invoice->invoice_number ) . '</h1>';
		$html .= '<p class="muted">تاریخ: ' . esc_html( (string) $invoice->created_at ) . '</p>';
		$html .= '<table><thead><tr><th>محصول</th><th>تعداد</th><th>قیمت واحد</th><th>مبلغ کل</th></tr></thead><tbody>';
		foreach ( $lines as $l ) {
			$html .= '<tr><td>' . $l['name'] . '</td><td>' . $l['qty'] . '</td><td>' . $l['price'] . '</td><td>' . $l['total'] . '</td></tr>';
		}
		$html .= '</tbody></table>';
		$html .= '<p style="text-align:left;font-weight:bold;font-size:16px">مجموع: ' . esc_html( Helpers::format_money( (float) $invoice->total ) ) . '</p>';
		$html .= '</body></html>';

		return array( 'ok' => true, 'html' => $html );
	}

	/* ================= Seller Dashboard ================= */

	private function read_seller_dashboard( array $args ): array {
		$seller_id = get_current_user_id();
		$accounting = new AccountingService();

		$balance = $accounting->get_balance( $seller_id );

		$commission_repo = new CommissionRepository();
		$commissions     = $commission_repo->paginate( array( 'seller_id' => $seller_id ), 1, 10 );

		$rows = array();
		foreach ( $commissions['rows'] ?? array() as $c ) {
			$rows[] = array( $c->id, esc_html( (string) $c->order_id ), esc_html( (string) $c->amount ), esc_html( $c->status ) );
		}

		return array(
			'ok'      => true,
			'title'   => __( 'داشبورد بازاریاب', 'bespari-core' ),
			'stats'   => array(
				array( 'label' => __( 'موجودی کیف پول', 'bespari-core' ), 'value' => Helpers::format_money( (float) $balance ) ),
			),
			'headers' => array( __( 'شناسه', 'bespari-core' ), __( 'سفارش', 'bespari-core' ), __( 'مبلغ', 'bespari-core' ), __( 'وضعیت', 'bespari-core' ) ),
			'rows'    => $rows,
			'pages'   => 1,
			'page'    => 1,
		);
	}

	private function write_seller_dashboard( int $id, array $input ): array {
		// درخواست تسویه از داشبورد بازاریاب.
		$service     = new PayoutService();
		$input['seller_id'] = get_current_user_id();
		$result = $service->request( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	/* ================= Users ================= */

	private function read_users( array $args ): array {
		$service = new UserService();
		$result  = $service->list( $args );

		$rows = array();
		foreach ( $result['rows'] as $u ) {
			$rows[] = array(
				$u['id'],
				esc_html( $u['login'] ),
				esc_html( $u['name'] ),
				esc_html( $u['email'] ),
				esc_html( $u['role_label'] ),
				(int) $u['tokens'],
			);
		}

		$role_options = array();
		foreach ( UserService::roles() as $key => $label ) {
			$role_options[] = array( 'value' => $key, 'label' => $label );
		}

		return array(
			'ok'        => true,
			'title'     => __( 'کاربران', 'bespari-core' ),
			'headers'   => array( __( 'شناسه', 'bespari-core' ), __( 'نام کاربری', 'bespari-core' ), __( 'نام', 'bespari-core' ), __( 'ایمیل', 'bespari-core' ), __( 'نقش', 'bespari-core' ), __( 'توکن‌ها', 'bespari-core' ) ),
			'rows'      => $rows,
			'pages'     => $result['pages'],
			'page'      => $result['page'],
			'filters'   => array(
				array( 'name' => 'role', 'type' => 'select', 'options' => $role_options, 'label' => __( 'نقش', 'bespari-core' ) ),
			),
			'form'      => array(
				'fields' => array(
					array( 'name' => 'user_login', 'label' => __( 'نام کاربری', 'bespari-core' ), 'type' => 'text', 'required' => true ),
					array( 'name' => 'display_name', 'label' => __( 'نام نمایشی', 'bespari-core' ), 'type' => 'text', 'required' => true ),
					array( 'name' => 'email', 'label' => __( 'ایمیل', 'bespari-core' ), 'type' => 'text', 'required' => true ),
					array( 'name' => 'role', 'label' => __( 'نقش', 'bespari-core' ), 'type' => 'select', 'options' => $role_options, 'required' => true ),
					array( 'name' => 'password', 'label' => __( 'رمز عبور (خالی = خودکار)', 'bespari-core' ), 'type' => 'password' ),
				),
			),
			'rowActions' => array(
				'token'  => __( 'صدور توکن', 'bespari-core' ),
				'delete' => __( 'حذف', 'bespari-core' ),
			),
		);
	}

	private function write_users( int $id, array $input ): array {
		$service = new UserService();
		$result  = $id ? $service->update( $id, $input ) : $service->create( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function action_users( int $id, string $action, array $params ): array {
		$service = new UserService();

		if ( 'token' === $action ) {
			$token = $service->issue_token( $id );

			if ( is_wp_error( $token ) ) {
				return array( 'ok' => false, 'message' => $token->get_error_message() );
			}

			return array( 'ok' => true, 'token' => $token );
		}

		if ( 'revoke_tokens' === $action ) {
			$result = $service->revoke_tokens( $id );

			if ( is_wp_error( $result ) ) {
				return array( 'ok' => false, 'message' => $result->get_error_message() );
			}

			return array( 'ok' => true );
		}

		return array( 'ok' => false, 'message' => __( 'عملیات نامعتبر است.', 'bespari-core' ) );
	}

	private function delete_users( int $id ): array {
		$service = new UserService();
		$result  = $service->delete( $id );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	/* ================= Employees & Permissions ================= */

	private function read_employees( array $args ): array {
		$service = new EmployeeService();
		$result  = $service->list( $args );

		$role_options = array();
		foreach ( EmployeeService::roles() as $key => $label ) {
			$role_options[] = array( 'value' => $key, 'label' => $label );
		}

		$menu_subjects = array();
		foreach ( PermissionService::menu_subjects() as $key => $label ) {
			$menu_subjects[] = array( 'value' => $key, 'label' => $label );
		}

		$rows        = array();
		$row_values  = array();

		foreach ( $result['rows'] as $e ) {
			$initial = (string) ( $e['first_name'] ? $e['first_name'] : $e['login'] );
			$initial = function_exists( 'mb_substr' ) ? mb_substr( $initial, 0, 1 ) : substr( $initial, 0, 1 );

			$photo = ! empty( $e['photo'] )
				? '<img src="' . esc_url( $e['photo'] ) . '" alt="" class="bp-avatar bp-avatar--sm">'
				: '<span class="bp-avatar bp-avatar--sm bp-avatar--empty">' . esc_html( $initial ) . '</span>';

			$rows[] = array(
				$e['id'],
				$photo,
				esc_html( trim( (string) $e['first_name'] . ' ' . (string) $e['last_name'] ) ),
				esc_html( $e['login'] ),
				esc_html( $e['mobile'] ? $e['mobile'] : '—' ),
				esc_html( $e['email'] ),
				esc_html( $e['role_label'] ),
				esc_html( $e['permissions'] ),
			);

			$row_values[ $e['id'] ] = $service->get_form_values( (int) $e['id'] );
		}

		// فیلدهای موجودیت‌ها (دسترسی به داده).
		$entity_fields = array();
		foreach ( PermissionService::entity_subjects() as $key => $info ) {
			$entity_fields[] = array(
				'name'    => 'allowed_' . $key,
				'label'   => $info[0],
				'type'    => 'checkbox_group',
				'options' => PermissionService::entity_options( $key ),
				'help'    => $info[1],
			);
		}

		return array(
			'ok'        => true,
			'title'     => __( 'کارمندان و دسترسی‌ها', 'bespari-core' ),
			'headers'   => array(
				__( 'شناسه', 'bespari-core' ),
				__( 'عکس', 'bespari-core' ),
				__( 'نام و نام خانوادگی', 'bespari-core' ),
				__( 'نام کاربری', 'bespari-core' ),
				__( 'موبایل', 'bespari-core' ),
				__( 'ایمیل', 'bespari-core' ),
				__( 'نقش', 'bespari-core' ),
				__( 'دسترسی‌ها', 'bespari-core' ),
			),
			'rows'      => $rows,
			'rowValues' => $row_values,
			'pages'     => $result['pages'],
			'page'      => $result['page'],
			'filters'   => array(
				array( 'name' => 'role', 'type' => 'select', 'options' => $role_options, 'label' => __( 'نقش', 'bespari-core' ) ),
			),
			'form'      => array(
				'wide'   => true,
				'fields' => array_merge(
					array(
						array( 'name' => 'photo', 'label' => __( 'عکس', 'bespari-core' ), 'type' => 'photo' ),
						array( 'name' => 'user_login', 'label' => __( 'نام کاربری', 'bespari-core' ), 'type' => 'text', 'required' => true ),
						array( 'name' => 'first_name', 'label' => __( 'نام', 'bespari-core' ), 'type' => 'text' ),
						array( 'name' => 'last_name', 'label' => __( 'نام خانوادگی', 'bespari-core' ), 'type' => 'text' ),
						array( 'name' => 'mobile', 'label' => __( 'موبایل', 'bespari-core' ), 'type' => 'text' ),
						array( 'name' => 'email', 'label' => __( 'ایمیل', 'bespari-core' ), 'type' => 'text', 'required' => true ),
						array( 'name' => 'role', 'label' => __( 'نقش', 'bespari-core' ), 'type' => 'select', 'options' => $role_options, 'required' => true ),
						array( 'name' => 'password', 'label' => __( 'رمز عبور (خالی = خودکار)', 'bespari-core' ), 'type' => 'password' ),
						array(
							'name'        => 'permissions',
							'label'       => __( 'دسترسی به منوها', 'bespari-core' ),
							'type'        => 'permission_matrix',
							'options'     => $menu_subjects,
							'modeOptions' => PermissionService::modes(),
							'help'        => __( 'برای هر منو حالت دسترسی را تعیین کنید.', 'bespari-core' ),
						),
						array( 'name' => 'channel_view', 'label' => __( 'نمایش کانال‌های فروش', 'bespari-core' ), 'type' => 'checkbox' ),
					),
					$entity_fields
				),
			),
			'rowActions' => array(
				'edit'             => __( 'ویرایش', 'bespari-core' ),
				'delete_photo'     => __( 'حذف عکس', 'bespari-core' ),
				'reset_permissions'=> __( 'بازنشانی دسترسی‌ها', 'bespari-core' ),
				'delete'           => __( 'حذف', 'bespari-core' ),
			),
		);
	}

	private function write_employees( int $id, array $input ): array {
		$service = new EmployeeService();
		$result  = $id ? $service->update( $id, $input ) : $service->create( $input );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true, 'id' => $result );
	}

	private function delete_employees( int $id ): array {
		$service = new EmployeeService();
		$result  = $service->delete( $id );

		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}

		return array( 'ok' => true );
	}

	private function action_employees( int $id, string $action, array $params ): array {
		$service = new EmployeeService();

		if ( 'delete_photo' === $action ) {
			$service->delete_photo( $id );
			return array( 'ok' => true );
		}

		if ( 'reset_permissions' === $action ) {
			PermissionService::clear_grants( $id );
			return array( 'ok' => true );
		}

		return array( 'ok' => false, 'message' => __( 'عملیات نامعتبر است.', 'bespari-core' ) );
	}
}
