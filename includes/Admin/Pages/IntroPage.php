<?php
/**
 * صفحه‌ی معرفی افزونه — تنها منوی افزونه در wp-admin.
 *
 * @package Bespari\Admin\Pages
 */

namespace Bespari\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IntroPage extends BasePage {

	protected string $slug = 'bespari-erp';
	protected string $cap  = 'bespari_read_dashboard';

	public function render(): void {
		if ( ! $this->can_access() ) {
			wp_die( esc_html__( 'شما دسترسی لازم را ندارید.', 'bespari-core' ) );
		}

		$panel_url = home_url( '/panel/' );

		$features = array(
			__( 'مدیریت کانال‌های فروش (دیجی‌کالا، باسلام، ترب و …)', 'bespari-core' ),
			__( 'ثبت و مدیریت برندها و محصولات', 'bespari-core' ),
			__( 'قیمت‌گذاری پویا با قوانین قابل تنظیم', 'bespari-core' ),
			__( 'ثبت سریع سفارش (POS) و مدیریت سفارشات', 'bespari-core' ),
			__( 'خط تولید و برنامه‌ریزی روزانه', 'bespari-core' ),
			__( 'تسویه کانال‌ها و صورتحساب‌ها', 'bespari-core' ),
			__( 'مدیریت بازاریاب‌ها، پورسانت و پرداخت‌ها', 'bespari-core' ),
			__( 'انبار، ارسال‌ها و مرجوعی‌ها', 'bespari-core' ),
			__( 'حسابداری و گزارشات مالی', 'bespari-core' ),
			__( 'مدیریت کاربران و سطوح دسترسی', 'bespari-core' ),
		);

		?>
		<div class="wrap bespari-wrap">
			<div class="bespari-intro">
				<div class="bespari-intro__card">
					<div class="bespari-intro__icon"><span class="dashicons dashicons-cart"></span></div>
					<h1 class="bespari-intro__title">بسپاری <span>ERP</span></h1>
					<p class="bespari-intro__version">
						<?php esc_html_e( 'نسخه', 'bespari-core' ); ?>
						<?php echo esc_html( BESPARI_VERSION ); ?>
					</p>
					<p class="bespari-intro__desc">
						<?php esc_html_e( 'سیستم یکپارچه مدیریت فروش برای کسب‌وکارهای آنلاین. همه‌ی امکانات از طریق پنل مدیریت مستقل در دسترس است.', 'bespari-core' ); ?>
					</p>
					<a class="bespari-intro__button" href="<?php echo esc_url( $panel_url ); ?>" target="_blank">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'ورود به پنل مدیریت', 'bespari-core' ); ?>
					</a>
					<p class="bespari-intro__url"><?php echo esc_html( $panel_url ); ?></p>
				</div>

				<div class="bespari-intro__features">
					<h2><?php esc_html_e( 'مزایا و امکانات', 'bespari-core' ); ?></h2>
					<ul>
						<?php foreach ( $features as $f ) : ?>
							<li><span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html( $f ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="bespari-intro__system">
					<h2><?php esc_html_e( 'اطلاعات سیستم', 'bespari-core' ); ?></h2>
					<dl>
						<dt><?php esc_html_e( 'نسخه افزونه', 'bespari-core' ); ?></dt>
						<dd><?php echo esc_html( BESPARI_VERSION ); ?></dd>
						<dt><?php esc_html_e( 'نسخه دیتابیس', 'bespari-core' ); ?></dt>
						<dd><?php echo esc_html( (string) get_option( 'bespari_db_version' ) ); ?></dd>
						<dt><?php esc_html_e( 'وضعیت ووکامرس', 'bespari-core' ); ?></dt>
						<dd>
							<?php
							if ( function_exists( 'Bespari\Support\Helpers\woocommerce_is_active' ) ) {
								$wc = \Bespari\Support\Helpers\woocommerce_is_active();
							} else {
								$wc = false;
							}
							echo $wc ? esc_html__( 'فعال', 'bespari-core' ) : esc_html__( 'غیرفعال', 'bespari-core' );
							?>
						</dd>
					</dl>
				</div>
			</div>
		</div>
		<?php
	}
}
