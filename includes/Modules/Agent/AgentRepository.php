<?php
/**
 * ریپازیتوری سایت‌های اقماری.
 *
 * @package Bespari\Modules\Agent
 */

namespace Bespari\Modules\Agent;

use Bespari\Support\Helpers;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgentRepository {

	/**
	 * دریافت یک agent با آی‌دی (با نام کانال/بازاریاب).
	 */
	public function get_by_id( int $id ): ?AgentModel {
		global $wpdb;
		$table    = Helpers::table( 'agents' );
		$channels = Helpers::table( 'channels' );
		$users    = $GLOBALS['wpdb']->users;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT a.*, c.name AS channel_name, u.display_name AS seller_name
			FROM {$table} a
			LEFT JOIN {$channels} c ON c.id = a.channel_id
			LEFT JOIN {$users} u ON u.ID = a.seller_id
			WHERE a.id = %d", // phpcs:ignore
			$id
		), ARRAY_A );

		return $row ? AgentModel::from_row( $row ) : null;
	}

	/**
	 * دریافت agent با api_key (برای HMAC).
	 */
	public function find_by_api_key( string $api_key ): ?AgentModel {
		global $wpdb;
		$table = Helpers::table( 'agents' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE api_key = %s LIMIT 1",
			$api_key
		), ARRAY_A );

		return $row ? AgentModel::from_row( $row ) : null;
	}

	/**
	 * لیست همه agentها.
	 *
	 * @return AgentModel[]
	 */
	public function all(): array {
		global $wpdb;
		$table    = Helpers::table( 'agents' );
		$channels = Helpers::table( 'channels' );
		$users    = $GLOBALS['wpdb']->users;

		$rows = $wpdb->get_results(
			"SELECT a.*, c.name AS channel_name, u.display_name AS seller_name
			FROM {$table} a
			LEFT JOIN {$channels} c ON c.id = a.channel_id
			LEFT JOIN {$users} u ON u.ID = a.seller_id
			ORDER BY a.id DESC", // phpcs:ignore
			ARRAY_A
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = AgentModel::from_row( $row );
		}
		return $items;
	}

	/**
	 * آیا api_key از قبل موجود است؟
	 */
	public function api_key_exists( string $api_key, int $exclude_id = 0 ): bool {
		global $wpdb;
		$table = Helpers::table( 'agents' );

		if ( $exclude_id > 0 ) {
			return (bool) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE api_key = %s AND id != %d LIMIT 1",
				$api_key,
				$exclude_id
			) );
		}

		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE api_key = %s LIMIT 1",
			$api_key
		) );
	}

	/**
	 * ثبت لاگ اتصال.
	 */
	public function log( int $agent_id, string $direction, string $endpoint, string $status, string $message = '' ): void {
		global $wpdb;

		$wpdb->insert( Helpers::table( 'agent_log' ), array(
			'agent_id'   => $agent_id,
			'direction'  => sanitize_key( $direction ),
			'endpoint'   => sanitize_text_field( $endpoint ),
			'status'     => sanitize_key( $status ),
			'message'    => sanitize_textarea_field( $message ),
			'created_at' => Helpers::now(),
		) );

		// Trim: فقط ۱۰۰۰ رکورد آخر.
		$log_table = Helpers::table( 'agent_log' );
		$keep      = (int) $wpdb->get_var( "SELECT id FROM {$log_table} ORDER BY id DESC LIMIT 1 OFFSET 1000" ); // phpcs:ignore
		if ( $keep > 0 ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$log_table} WHERE id < %d", $keep ) ); // phpcs:ignore
		}
	}

	/**
	 * آخرین لاگ‌های یک agent.
	 *
	 * @return array
	 */
	public function get_logs( int $agent_id, int $limit = 20 ): array {
		global $wpdb;
		$log_table = Helpers::table( 'agent_log' );

		return (array) $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$log_table} WHERE agent_id = %d ORDER BY id DESC LIMIT %d", // phpcs:ignore
			$agent_id,
			max( 1, $limit )
		) );
	}

	/**
	 * درج/به‌روزرسانی.
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$result = $wpdb->insert( Helpers::table( 'agents' ), $data );
		return false === $result ? 0 : (int) $wpdb->insert_id;
	}

	public function update( int $id, array $data ): bool {
		global $wpdb;
		return false !== $wpdb->update( Helpers::table( 'agents' ), $data, array( 'id' => $id ) );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->delete( Helpers::table( 'agents' ), array( 'id' => $id ) );
	}
}
