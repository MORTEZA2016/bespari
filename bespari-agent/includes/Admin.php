<?php
/**
 * Admin — صفحه تنظیمات افزونه اقماری.
 *
 * @package BespariAgent
 */

namespace BespariAgent;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/**
	 * ثبت هوک‌ها.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_bespari_agent_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_bespari_agent_test', array( __CLASS__, 'handle_test' ) );
	}

	/**
	 * فعال‌سازی — cronها بعد از اولین پیکربندی ثبت می‌شوند.
	 */
	public static function activate(): void {
		// بدون seed خاص — تنظیمات بعداً در صفحه ادمین ذخیره می‌شود.
	}

	/**
	 * غیرفعال‌سازی — پاک‌سازی cronها.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'bespari_agent_orders_cron' );
		wp_clear_scheduled_hook( 'bespari_agent_prices_cron' );
	}

	/**
	 * منوی ادمین.
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'بسپاری Agent', 'bespari-agent' ),
			__( 'بسپاری Agent', 'bespari-agent' ),
			'manage_options',
			'bespari-agent',
			array( __CLASS__, 'render_page' ),
			'dashicons-networking'
		);
	}

	/**
	 * ذخیره تنظیمات + ثبت cron در صورت فعال بودن.
	 */
	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-agent' ) );
		}
		check_admin_referer( 'bespari_agent_save', 'bespari_agent_nonce' );

		$raw    = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore
		$secret = isset( $raw['api_secret'] ) ? sanitize_text_field( $raw['api_secret'] ) : '';

		$existing = Http::get_settings();
		$new      = array(
			'enabled'    => empty( $raw['enabled'] ) ? 0 : 1,
			'site_url'   => isset( $raw['site_url'] ) ? esc_url_raw( $raw['site_url'] ) : '',
			'api_key'    => isset( $raw['api_key'] ) ? sanitize_text_field( $raw['api_key'] ) : '',
			'api_secret' => '' !== $secret ? $secret : (string) $existing['api_secret'],
		);

		update_option( 'bespari_agent_settings', $new );

		// ثبت cron در صورت فعال بودن (idempotent).
		if ( ! empty( $new['enabled'] ) ) {
			self::schedule_crons();
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'bespari-agent', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * ثبت cronها.
	 */
	public static function schedule_crons(): void {
		if ( ! wp_next_scheduled( 'bespari_agent_orders_cron' ) ) {
			wp_schedule_event( time() + 120, 'bespari_agent_5min', 'bespari_agent_orders_cron' );
		}
		if ( ! wp_next_scheduled( 'bespari_agent_prices_cron' ) ) {
			wp_schedule_event( time() + 180, 'bespari_agent_30min', 'bespari_agent_prices_cron' );
		}
	}

	/**
	 * تست اتصال (handshake).
	 */
	public static function handle_test(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-agent' ) );
		}
		check_admin_referer( 'bespari_agent_test', 'bespari_agent_nonce' );

		$result = Http::signed_request( 'POST', 'bespari/v1/agent/handshake' );

		Http::log( 'out', 'handshake', $result['ok'] ? 'ok' : 'error', $result['error'] );

		wp_safe_redirect( add_query_arg( array(
			'page'   => 'bespari-agent',
			'test'   => $result['ok'] ? 'ok' : 'fail',
			'testmsg' => urlencode( $result['ok'] ? 'اتصال برقرار شد.' : $result['error'] ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * رندر صفحه تنظیمات.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-agent' ) );
		}

		$settings = Http::get_settings();
		?>
		<div class="wrap" dir="rtl" style="font-family: Vazirmatn, Tahoma, sans-serif;">
			<h1><?php esc_html_e( 'بسپاری Agent Connector', 'bespari-agent' ); ?></h1>
			<p><?php esc_html_e( 'اتصال این سایت به سایت مادر بسپاری ERP — ارسال خودکار سفارش‌ها و دریافت قیمت/موجودی.', 'bespari-agent' ); ?></p>

			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'تنظیمات ذخیره شد.', 'bespari-agent' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['test'] ) ) : // phpcs:ignore ?>
				<div class="notice <?php echo 'ok' === $_GET['test'] ? 'notice-success' : 'notice-error'; ?>"><p>
					<?php echo esc_html( urldecode( isset( $_GET['testmsg'] ) ? sanitize_text_field( wp_unslash( $_GET['testmsg'] ) ) : '' ) ); // phpcs:ignore ?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bespari_agent_save', 'bespari_agent_nonce' ); ?>
				<input type="hidden" name="action" value="bespari_agent_save" />
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'فعال', 'bespari-agent' ); ?></th>
						<td><label><input type="checkbox" name="settings[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> /> <?php esc_html_e( 'سینک فعال باشد', 'bespari-agent' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="bespari-agent-url"><?php esc_html_e( 'آدرس سایت مادر', 'bespari-agent' ); ?></label></th>
						<td><input id="bespari-agent-url" type="url" name="settings[site_url]" dir="ltr" class="regular-text" value="<?php echo esc_attr( $settings['site_url'] ); ?>" placeholder="https://hq.example.com" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bespari-agent-key"><?php esc_html_e( 'API Key', 'bespari-agent' ); ?></label></th>
						<td><input id="bespari-agent-key" type="text" name="settings[api_key]" dir="ltr" class="regular-text" value="<?php echo esc_attr( $settings['api_key'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bespari-agent-secret"><?php esc_html_e( 'API Secret', 'bespari-agent' ); ?></label></th>
						<td>
							<input id="bespari-agent-secret" type="password" name="settings[api_secret]" dir="ltr" class="regular-text" value="" placeholder="<?php echo esc_attr( ! empty( $settings['api_secret'] ) ? __( 'ذخیره شده — برای تغییر مقدار جدید وارد کنید', 'bespari-agent' ) : '' ); ?>" autocomplete="new-password" />
							<p class="description"><?php esc_html_e( 'Secret پس از ذخیره نمایش داده نمی‌شود. از سایت مادر (صفحه اتصال‌ها) دریافت کنید.', 'bespari-agent' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'ذخیره تنظیمات', 'bespari-agent' ), 'primary', 'submit' ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'تست اتصال', 'bespari-agent' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bespari_agent_test', 'bespari_agent_nonce' ); ?>
				<input type="hidden" name="action" value="bespari_agent_test" />
				<?php submit_button( __( 'برقراری ارتباط آزمایشی', 'bespari-agent' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'آخرین فعالیت‌ها', 'bespari-agent' ); ?></h2>
			<?php
			$logs = get_option( 'bespari_agent_log', array() );
			if ( empty( $logs ) ) {
				echo '<p>' . esc_html__( 'فعالیتی ثبت نشده است.', 'bespari-agent' ) . '</p>';
			} else {
				echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'جهت', 'bespari-agent' ) . '</th><th>' . esc_html__( 'Endpoint', 'bespari-agent' ) . '</th><th>' . esc_html__( 'وضعیت', 'bespari-agent' ) . '</th><th>' . esc_html__( 'پیام', 'bespari-agent' ) . '</th><th>' . esc_html__( 'زمان', 'bespari-agent' ) . '</th></tr></thead><tbody>';
				foreach ( $logs as $log ) {
					echo '<tr><td>' . esc_html( $log['direction'] ) . '</td><td>' . esc_html( $log['endpoint'] ) . '</td><td>' . esc_html( $log['status'] ) . '</td>';
					echo '<td>' . esc_html( $log['message'] ?: '—' ) . '</td><td>' . esc_html( $log['time'] ) . '</td></tr>';
				}
				echo '</tbody></table>';
			}
			?>
		</div>
		<?php
	}
}
