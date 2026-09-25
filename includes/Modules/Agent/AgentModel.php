<?php
/**
 * مدل سایت اقماری (Agent Connector).
 *
 * @package Bespari\Modules\Agent
 */

namespace Bespari\Modules\Agent;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgentModel {

	public int $id = 0;
	public string $name = '';
	public string $site_url = '';
	public string $api_key = '';
	public string $api_secret = '';
	public int $channel_id = 0;
	public int $seller_id = 0;
	public string $status = 'active';
	public string $last_sync_at = '';
	public string $created_at = '';
	public string $updated_at = '';

	/** نام کانال (join). */
	public string $channel_name = '';

	/** نام بازاریاب (join). */
	public string $seller_name = '';

	public static function from_row( array $row ): self {
		$m = new self();
		foreach ( $row as $key => $val ) {
			if ( null === $val && ! in_array( $key, array( 'last_sync_at' ), true ) ) {
				continue;
			}
			if ( property_exists( $m, $key ) ) {
				switch ( $key ) {
					case 'id':
					case 'channel_id':
					case 'seller_id':
						$m->$key = (int) $val;
						break;
					default:
						$m->$key = (string) $val;
				}
			}
		}
		return $m;
	}

	public function status_label(): string {
		$labels = array(
			'active'   => __( 'فعال', 'bespari-core' ),
			'inactive' => __( 'غیرفعال', 'bespari-core' ),
			'blocked'  => __( 'مسدود', 'bespari-core' ),
		);
		return $labels[ $this->status ] ?? $this->status;
	}
}
