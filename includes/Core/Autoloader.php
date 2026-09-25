<?php
/**
 * PSR-4 Autoloader با پیشوند Bespari\.
 *
 * @package Bespari\Core
 */

namespace Bespari\Core;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoloader {

	/**
	 * ثبت Autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * لود کلاس بر اساس namespace.
	 *
	 * @param string $class نام کامل کلاس.
	 */
	public static function autoload( string $class ): void {
		$prefix = 'Bespari\\';

		if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = BESPARI_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
