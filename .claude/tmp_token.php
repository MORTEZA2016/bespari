<?php
/**
 * صدور توکن یک‌بار برای ادمین (فقط برای تست dev).
 */
require 'C:/xampp/htdocs/delori/wp-load.php';

use Bespari\Api\AppAuth;

$login = $argv[1] ?? 'admin';
$user  = get_user_by( 'login', $login );
if ( ! $user ) {
	echo "USER-NOT-FOUND\n";
	exit( 1 );
}

echo 'user_id: ' . $user->ID . "\n";
echo 'roles: ' . implode( ',', (array) $user->roles ) . "\n";

$reflect = new ReflectionClass( AppAuth::class );
if ( $reflect->hasMethod( 'issue' ) ) {
	$token = AppAuth::issue( $user );
	echo 'token: ' . ( is_wp_error( $token ) ? 'ERR:' . $token->get_error_message() : $token ) . "\n";
} else {
	echo "AppAuth::issue NOT FOUND. methods: " . implode( ',', array_map( function ( $m ) {
		return $m->getName(); }, $reflect->getMethods() ) ) . "\n";
}
