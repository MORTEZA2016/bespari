<?php
/**
 * اپ مستقل بسپاری — shell تک‌صفحه‌ای (SPA) سرو‌شده در /panel/.
 *
 * این صفحه فقط HTML خالی + CSS/JS را سرو می‌کند؛
 * احراز هویت و داده‌ها همه از طریق REST با توکن انجام می‌شود.
 *
 * @package Bespari\Admin\Panel
 */

namespace Bespari\Admin\Panel;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AppPage {

	public function register(): void {
		// rewrite برای /panel/ (URL زیبا).
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 0 );

		// fallback بدون نیاز به flush (admin-post).
		add_action( 'admin_post_bespari_app', array( $this, 'render' ) );
		add_action( 'admin_post_nopriv_bespari_app', array( $this, 'render' ) );
	}

	/**
	 * ثبت قانون بازنویسی /panel/.
	 */
	public function register_rewrite(): void {
		add_rewrite_rule( '^panel/?$', 'index.php?bespari_app=1', 'top' );

		// یک‌بار flush پس از هر نسخه.
		if ( get_option( 'bespari_app_rewrite_ver' ) !== BESPARI_VERSION ) {
			flush_rewrite_rules();
			update_option( 'bespari_app_rewrite_ver', BESPARI_VERSION );
		}
	}

	/**
	 * متغیر کوئری اختصاصی.
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = 'bespari_app';
		return $vars;
	}

	/**
	 * اگر درخواست اپ بود، shell را سرو کرده و قطع کن.
	 */
	public function maybe_render(): void {
		if ( ! get_query_var( 'bespari_app' ) ) {
			return;
		}
		$this->render();
	}

	/**
	 * URL اپ.
	 */
	public static function url(): string {
		return home_url( '/panel/' );
	}

	/**
	 * رندر shell اپ.
	 */
	public function render(): void {
		$css_url = BESPARI_URL . 'assets/css/app.css';
		$js_url  = BESPARI_URL . 'assets/js/app.js';
		$cfg     = array(
			'restUrl'   => esc_url_raw( rest_url( 'bespari/v1/' ) ),
			'homeUrl'   => home_url( '/panel/' ),
			'version'   => BESPARI_VERSION,
			'brandName' => get_bloginfo( 'name' ),
		);

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex,nofollow" />
<title>پنل بسپاری ERP</title>
<link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>?ver=<?php echo rawurlencode( BESPARI_VERSION ); ?>" />
</head>
<body class="bp-app-body">
<div id="bp-app">
	<div class="bp-boot">در حال بارگذاری پنل…</div>
</div>
<script>window.BP_CONFIG = <?php echo wp_json_encode( $cfg ); ?>;</script>
<script src="<?php echo esc_url( $js_url ); ?>?ver=<?php echo rawurlencode( BESPARI_VERSION ); ?>"></script>
</body>
</html>
		<?php
		exit;
	}
}
