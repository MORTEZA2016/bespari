<?php
/**
 * REST داده‌های پنل مستقل — خروجی آماده رندر {title, stats, headers, rows}.
 *
 * @package Bespari\Api
 */

namespace Bespari\Api;

use Bespari\Support\Helpers;
use Bespari\Modules\Optimization\Optimization;
use Bespari\Modules\Order\OrderRepository;
use Bespari\Modules\Product\ProductRepository;
use Bespari\Modules\Channel\ChannelRepository;
use Bespari\Modules\Seller\SellerRepository;
use Bespari\Modules\Commission\CommissionRepository;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PanelRestController {

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$views = array( 'dashboard', 'orders', 'products', 'channels', 'sellers', 'commissions', 'warehouse', 'reports' );

		foreach ( $views as $view ) {
			register_rest_route( 'bespari/v1/panel', '/' . $view, array(
				'methods'             => 'GET',
				'callback'            => function ( \WP_REST_Request $request ) use ( $view ) {
					return $this->handle( $view, $request );
				},
				'permission_callback' => function ( \WP_REST_Request $request ) use ( $view ) {
					return $this->can_view( $view, $request );
				},
			) );
		}
	}

	/**
	 * احراز هویت + گارد cap منو.
	 */
	private function can_view( string $view, \WP_REST_Request $request ): bool {
		if ( ! AppAuth::authenticate( $request ) ) {
			return false;
		}

		$menus = AppAuth::menus();

		if ( ! isset( $menus[ $view ] ) ) {
			return false;
		}

		return AppAuth::user_can( wp_get_current_user(), $menus[ $view ][1] );
	}

	/**
	 * کاربر فقط بازاریاب است (سود/همه داده‌ها را نمی‌بیند).
	 */
	private function is_seller_only(): bool {
		return ! current_user_can( 'bespari_read_dashboard' );
	}

	private function seller_scope(): int {
		return $this->is_seller_only() ? get_current_user_id() : 0;
	}

	private function fmt( $n ): string {
		return Helpers::format_money( (float) $n );
	}

	private function badge( string $label, string $cls ): string {
		return '<span class="bp-badge bp-badge--' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</span>';
	}

	private function order_status_badge( string $status ): string {
		$map = array(
			'pending'   => array( 'در انتظار', 'warning' ),
			'confirmed' => array( 'تاییدشده', 'info' ),
			'prepared'  => array( 'آماده‌سازی', 'info' ),
			'produced'  => array( 'تولید شده', 'info' ),
			'shipped'   => array( 'ارسال‌شده', 'info' ),
			'delivered' => array( 'تحویل‌شده', 'success' ),
			'cancelled' => array( 'لغوشده', 'danger' ),
		);
		$m   = $map[ $status ] ?? array( $status, 'muted' );
		return $this->badge( $m[0], $m[1] );
	}

	private function settlement_badge( string $status ): string {
		$map = array(
			'pending'         => array( 'در انتظار تسویه', 'warning' ),
			'partial_settled' => array( 'تسویه جزئی', 'info' ),
			'settled'         => array( 'تسویه‌شده', 'success' ),
		);
		$m   = $map[ $status ] ?? array( $status, 'muted' );
		return $this->badge( $m[0], $m[1] );
	}

	private function commission_badge( string $status ): string {
		$map = array(
			'pending'   => array( 'در انتظار', 'warning' ),
			'approved'  => array( 'تاییدشده', 'info' ),
			'paid'      => array( 'پرداخت‌شده', 'success' ),
			'cancelled' => array( 'لغوشده', 'danger' ),
		);
		$m   = $map[ $status ] ?? array( $status, 'muted' );
		return $this->badge( $m[0], $m[1] );
	}

	/**
	 * مسیریابی به view متناظر.
	 */
	private function handle( string $view, \WP_REST_Request $request ): \WP_REST_Response {
		switch ( $view ) {
			case 'orders':
				$data = $this->view_orders( $request );
				break;
			case 'products':
				$data = $this->view_products();
				break;
			case 'channels':
				$data = $this->view_channels();
				break;
			case 'sellers':
				$data = $this->view_sellers();
				break;
			case 'commissions':
				$data = $this->view_commissions();
				break;
			case 'warehouse':
				$data = $this->view_warehouse();
				break;
			case 'reports':
				$data = $this->view_reports();
				break;
			default:
				$data = $this->view_dashboard();
		}

		$data['title'] = AppAuth::menus()[ $view ][0] ?? __( 'پنل', 'bespari-core' );

		return new \WP_REST_Response( array_merge( array( 'ok' => true ), $data ) );
	}

	/* ---------- نماها ---------- */

	private function view_dashboard(): array {
		global $wpdb;
		$orders = Helpers::table( 'orders' );
		$seller = $this->seller_scope();

		// کش کوتاه‌مدت آمار (۱۵ دقیقه) — باطل می‌شود هنگام تغییر سفارش.
		$scope = $seller ? 'seller_' . $seller : 'all';
		$cached = Optimization::get_stats( get_current_user_id(), 'dashboard_' . $scope );
		if ( null !== $cached ) {
			return $cached;
		}

		$where  = $seller ? $wpdb->prepare( 'WHERE seller_id = %d', $seller ) : '';
		$stats  = $wpdb->get_row( "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_net),0) AS net, COALESCE(SUM(total_profit),0) AS profit FROM {$orders} {$where}" ); // phpcs:ignore
		$pend_q = $seller
			? $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE seller_id = %d AND settlement_status = 'pending'", $seller )
			: "SELECT COUNT(*) FROM {$orders} WHERE settlement_status = 'pending'";
		$pending = (int) $wpdb->get_var( $pend_q ); // phpcs:ignore

		$stat_cards = array(
			array( 'label' => __( 'کل سفارشات', 'bespari-core' ), 'value' => number_format_i18n( (int) $stats->cnt ) ),
			array( 'label' => __( 'مجموع فروش خالص', 'bespari-core' ), 'value' => $this->fmt( $stats->net ) ),
		);

		if ( ! $this->is_seller_only() ) {
			$stat_cards[] = array( 'label' => __( 'سود کل', 'bespari-core' ), 'value' => $this->fmt( $stats->profit ) );
		}

		$stat_cards[] = array( 'label' => __( 'در انتظار تسویه', 'bespari-core' ), 'value' => number_format_i18n( $pending ) );

		$rows = ( new OrderRepository() )->paginate( array( 'seller_id' => $seller ), 1, 10 );

		$out = array();
		foreach ( (array) ( $rows['items'] ?? array() ) as $o ) {
			$out[] = array(
				esc_html( $o->order_number ),
				esc_html( $o->channel_name ?: '—' ),
				esc_html( $o->customer_name ?: '—' ),
				esc_html( $this->fmt( $o->total_net ) ),
				$this->order_status_badge( (string) $o->status ),
			);
		}

		$result = array(
			'stats'   => $stat_cards,
			'section' => __( 'آخرین سفارشات', 'bespari-core' ),
			'headers' => array( 'شماره', 'کانال', 'مشتری', 'خالص', 'وضعیت' ),
			'rows'    => $out,
		);

		Optimization::set_stats( get_current_user_id(), 'dashboard_' . $scope, $result );

		return $result;
	}

	private function view_orders( \WP_REST_Request $request ): array {
		$seller  = $this->seller_scope();
		$per_page = 20;
		$paged    = max( 1, (int) ( $request->get_param( 'paged' ) ?: 1 ) );

		$res = ( new OrderRepository() )->paginate( array( 'seller_id' => $seller ), $paged, $per_page );

		// بازاریاب می‌تواند سفارش‌های تولیدشده را ارسال کند.
		$can_ship = $this->is_seller_only() || current_user_can( 'bespari_manage_orders' );

		$out = array();
		foreach ( (array) ( $res['items'] ?? array() ) as $o ) {
			$row = array(
				esc_html( $o->order_number ),
				esc_html( $o->channel_name ?: '—' ),
				esc_html( $o->customer_name ?: '—' ),
				'credit' === $o->sale_type ? 'اعتباری' : 'نقدی',
				esc_html( $this->fmt( $o->total_gross ) ),
				esc_html( $this->fmt( $o->total_net ) ),
				$this->order_status_badge( (string) $o->status ),
				$this->settlement_badge( (string) $o->settlement_status ),
				(string) $o->ordered_at ? esc_html( Helpers::to_jalali_date( (string) $o->ordered_at ) ) : '—',
			);

			if ( $can_ship ) {
				$row[] = 'produced' === $o->status
					? '<button type="button" class="bp-ship-btn" data-id="' . esc_attr( (string) $o->id ) . '">' . esc_html__( 'ارسال شد', 'bespari-core' ) . '</button>'
					: '—';
			}

			$out[] = $row;
		}

		$total = (int) ( $res['total'] ?? 0 );

		$headers = array( 'شماره', 'کانال', 'مشتری', 'نوع فروش', 'ناخالص', 'خالص', 'وضعیت', 'تسویه', 'تاریخ' );
		if ( $can_ship ) {
			$headers[] = __( 'عملیات', 'bespari-core' );
		}

		return array(
			'headers' => $headers,
			'rows'    => $out,
			'pages'   => max( 1, (int) ceil( $total / $per_page ) ),
			'page'    => $paged,
		);
	}

	private function view_products(): array {
		$res = ( new ProductRepository() )->paginate( array( 'per_page' => 50, 'page' => 1, 'status' => -1 ) );

		$out = array();
		foreach ( (array) ( $res['items'] ?? array() ) as $p ) {
			$out[] = array(
				esc_html( $p->name ),
				esc_html( $p->sku ?: '—' ),
				esc_html( $p->brand_name ?: '—' ),
				esc_html( $this->fmt( $p->base_price ) ),
				esc_html( number_format_i18n( (int) $p->stock ) ),
				(int) $p->status ? $this->badge( 'فعال', 'success' ) : $this->badge( 'غیرفعال', 'muted' ),
			);
		}

		return array(
			'headers' => array( 'نام', 'SKU', 'برند', 'قیمت پایه', 'موجودی', 'وضعیت' ),
			'rows'    => $out,
		);
	}

	private function view_channels(): array {
		$out = array();
		foreach ( (array) ( new ChannelRepository() )->get_all( false ) as $ch ) {
			$out[] = array(
				esc_html( $ch->name ),
				esc_html( $ch->settlement_label() ),
				esc_html( number_format_i18n( (float) $ch->commission_percent ) ) . '٪',
				(int) $ch->status ? $this->badge( 'فعال', 'success' ) : $this->badge( 'غیرفعال', 'muted' ),
			);
		}

		return array(
			'headers' => array( 'کانال', 'حالت تسویه', 'درصد پورسانت', 'وضعیت' ),
			'rows'    => $out,
		);
	}

	private function view_sellers(): array {
		$res = ( new SellerRepository() )->paginate( array(), 1, 50 );

		$out = array();
		foreach ( (array) ( $res['items'] ?? array() ) as $s ) {
			$out[] = array(
				esc_html( $s->display_name ),
				esc_html( $s->email ),
				'active' === $s->seller_status ? $this->badge( 'فعال', 'success' ) : $this->badge( 'غیرفعال', 'muted' ),
			);
		}

		return array(
			'headers' => array( 'نام', 'ایمیل', 'وضعیت' ),
			'rows'    => $out,
		);
	}

	private function view_commissions(): array {
		$seller = $this->seller_scope();

		$data  = array();
		$stats = array();

		if ( $seller ) {
			$balance = ( new CommissionRepository() )->get_seller_balance( $seller );
			foreach ( array( 'pending' => 'در انتظار', 'approved' => 'تاییدشده', 'paid' => 'پرداخت‌شده' ) as $k => $lbl ) {
				$stats[] = array( 'label' => $lbl, 'value' => $this->fmt( $balance[ $k ] ?? 0 ) );
			}
		}

		$res = ( new CommissionRepository() )->paginate( array( 'seller_id' => $seller ), 1, 30 );

		$out = array();
		foreach ( (array) ( $res['items'] ?? array() ) as $c ) {
			$out[] = array(
				esc_html( $c->order_number ?: '—' ),
				esc_html( $c->seller_name ?: '—' ),
				esc_html( $this->fmt( $c->base_amount ) ),
				esc_html( number_format_i18n( (float) $c->percent ) ) . '٪',
				esc_html( $this->fmt( $c->amount ) ),
				$this->commission_badge( (string) $c->status ),
			);
		}

		$data['stats']   = $stats;
		$data['headers'] = array( 'سفارش', 'بازاریاب', 'مبنا', 'درصد', 'مبلغ', 'وضعیت' );
		$data['rows']    = $out;

		return $data;
	}

	private function view_warehouse(): array {
		global $wpdb;
		$products = Helpers::table( 'products' );

		$rows_db = $wpdb->get_results( "SELECT name, sku, stock, low_stock_threshold FROM {$products} ORDER BY stock ASC LIMIT 50" ); // phpcs:ignore

		$out = array();
		foreach ( (array) $rows_db as $p ) {
			$low  = (int) $p->stock <= (int) $p->low_stock_threshold;
			$out[] = array(
				esc_html( $p->name ),
				esc_html( $p->sku ?: '—' ),
				esc_html( number_format_i18n( (int) $p->stock ) ),
				$low ? $this->badge( 'کم‌موجود', 'danger' ) : $this->badge( 'مناسب', 'success' ),
			);
		}

		return array(
			'headers' => array( 'محصول', 'SKU', 'موجودی', 'وضعیت' ),
			'rows'    => $out,
		);
	}

	private function view_reports(): array {
		global $wpdb;
		$orders   = Helpers::table( 'orders' );
		$channels = Helpers::table( 'channels' );

		$rows_db = $wpdb->get_results( "SELECT c.name AS channel, COUNT(o.id) AS cnt, COALESCE(SUM(o.total_net),0) AS net, COALESCE(SUM(o.total_profit),0) AS profit FROM {$orders} o LEFT JOIN {$channels} c ON c.id = o.channel_id WHERE o.status != 'cancelled' GROUP BY o.channel_id ORDER BY net DESC" ); // phpcs:ignore

		$out = array();
		foreach ( (array) $rows_db as $r ) {
			$out[] = array(
				esc_html( $r->channel ?: '—' ),
				esc_html( number_format_i18n( (int) $r->cnt ) ),
				esc_html( $this->fmt( $r->net ) ),
				esc_html( $this->fmt( $r->profit ) ),
			);
		}

		return array(
			'section' => __( 'فروش به تفکیک کانال', 'bespari-core' ),
			'headers' => array( 'کانال', 'تعداد سفارش', 'فروش خالص', 'سود' ),
			'rows'    => $out,
		);
	}
}
