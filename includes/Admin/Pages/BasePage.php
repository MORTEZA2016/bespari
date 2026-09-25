<?php
/**
 * کلاس پایه برای صفحات ادمین افزونه.
 *
 * @package Bespari\Admin\Pages
 */

namespace Bespari\Admin\Pages;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BasePage {

	/**
	 * اسلاگ صفحه.
	 *
	 * @var string
	 */
	protected string $slug = '';

	/**
	 * قابلیت لازم برای نمایش صفحه.
	 *
	 * @var string
	 */
	protected string $cap = 'bespari_read_dashboard';

	/**
	 * بررسی دسترسی قبل از رندر.
	 *
	 * @return bool
	 */
	protected function can_access(): bool {
		return current_user_can( $this->cap );
	}

	/**
	 * نمایش پیام اعلان پس از عملیات (ذخیره/حذف/...).
	 */
	protected function render_notice(): void {
		$msg   = isset( $_GET['bespari_msg'] ) ? sanitize_key( wp_unslash( $_GET['bespari_msg'] ) ) : ''; // phpcs:ignore
		$error = isset( $_GET['bespari_error'] ) ? sanitize_text_field( wp_unslash( $_GET['bespari_error'] ) ) : ''; // phpcs:ignore

		if ( '' === $msg ) {
			return;
		}

		$texts = array(
			'saved'   => array( 'success', __( 'با موفقیت ذخیره شد.', 'bespari-core' ) ),
			'deleted' => array( 'success', __( 'با موفقیت حذف شد.', 'bespari-core' ) ),
			'toggled' => array( 'success', __( 'وضعیت تغییر کرد.', 'bespari-core' ) ),
			'synced'  => array( 'success', sprintf(
				__( '%s محصول از ووکامرس همگام‌سازی شد.', 'bespari-core' ),
				'<strong>' . (int) ( $_GET['synced_count'] ?? 0 ) . '</strong>' // phpcs:ignore
			) ),
			'error'   => array( 'error', $error ?: __( 'خطایی رخ داد.', 'bespari-core' ) ),
		);

		if ( ! isset( $texts[ $msg ] ) ) {
			return;
		}

		$type = $texts[ $msg ][0];
		$text = $texts[ $msg ][1];

		echo '<div class="bespari-notice bespari-notice--' . esc_attr( $type ) . '"><span>' . wp_kses_post( $text ) . '</span></div>';
	}

	/**
	 * خروجی هدر صفحه با عنوان و توضیح اختیاری.
	 *
	 * @param string $title عنوان.
	 * @param string $desc  توضیح.
	 */
	protected function page_header( string $title, string $desc = '' ): void {
		echo '<div class="bespari-page-header">';
		echo '<h1 class="bespari-page-title">' . esc_html( $title ) . '</h1>';
		if ( '' !== $desc ) {
			echo '<p class="bespari-page-desc">' . esc_html( $desc ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * نمایش تب ناوبری (برای صفحات چندتبی).
	 *
	 * @param string $current تب فعلی.
	 * @param array  $tabs آرایه تب‌ها [slug => label].
	 */
	protected function render_tabs( string $current, array $tabs ): void {
		echo '<nav class="bespari-tabs">';
		foreach ( $tabs as $slug => $label ) {
			$active = $slug === $current ? ' is-active' : '';
			$url    = Helpers::admin_url( $this->slug, array( 'tab' => $slug ) );
			echo '<a class="bespari-tab' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * دریافت مقدار tab فعال.
	 *
	 * @param string $default پیش‌فرض.
	 * @return string
	 */
	protected function current_tab( string $default = 'list' ): string {
		return isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default; // phpcs:ignore
	}

	/**
	 * ساخت URL عملیات امن (با nonce).
	 *
	 * @param string $action نام عملیات.
	 * @param array  $args پارامترهای اضافی.
	 * @return string
	 */
	protected function admin_action_url( string $action, array $args = array() ): string {
		return wp_nonce_url(
			add_query_arg(
				array_merge( array( 'action' => 'bespari_' . $action ), $args ),
				admin_url( 'admin-post.php' )
			),
			'bespari_' . $action,
			'bespari_nonce'
		);
	}

	/**
	 * هر صفحه باید متد render را پیاده کند.
	 */
	abstract public function render(): void;
}
