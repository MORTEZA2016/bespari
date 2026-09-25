<?php
/**
 * Repository کانال‌های فروش.
 *
 * @package Bespari\Modules\Channel
 */

namespace Bespari\Modules\Channel;

use Bespari\Support\BaseRepository;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChannelRepository extends BaseRepository {

	protected string $table       = 'channels';
	protected string $model_class = ChannelModel::class;

	/**
	 * دریافت همه کانال‌ها (مرتب‌شده).
	 *
	 * @param bool $only_active فقط فعال‌ها.
	 * @return ChannelModel[]
	 */
	public function get_all( bool $only_active = false ): array {
		$where = $only_active ? array( 'status' => 1 ) : array();

		return $this->hydrate( $this->all( $where, 'sort_order', 'ASC' ) );
	}

	/**
	 * دریافت یک کانال بر اساس آی‌دی.
	 *
	 * @param int $id آی‌دی.
	 * @return ChannelModel|null
	 */
	public function get_by_id( int $id ): ?ChannelModel {
		$row = $this->find( $id );

		return $row ? ChannelModel::from_row( $row ) : null;
	}

	/**
	 * دریافت کانال بر اساس اسلاگ.
	 *
	 * @param string $slug اسلاگ.
	 * @return ChannelModel|null
	 */
	public function get_by_slug( string $slug ): ?ChannelModel {
		$rows = $this->all( array( 'slug' => $slug ) );

		return ! empty( $rows ) ? ChannelModel::from_row( $rows[0] ) : null;
	}

	/**
	 * دریافت بازه‌های تسویه یک کانال.
	 *
	 * @param int $channel_id آی‌دی کانال.
	 * @return array
	 */
	public function get_settlement_periods( int $channel_id ): array {
		global $wpdb;
		$table = \Bespari\Support\Helpers::table( 'channel_settlement_periods' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE channel_id = %d AND is_active = 1 ORDER BY sort_order ASC", // phpcs:ignore
				$channel_id
			)
		) ?: array();
	}

	/**
	 * ذخیره بازه‌های تسویه یک کانال (جایگزینی کامل).
	 *
	 * @param int   $channel_id آی‌دی کانال.
	 * @param array $periods    آرایه‌ای از بازه‌ها.
	 */
	public function save_settlement_periods( int $channel_id, array $periods ): void {
		global $wpdb;
		$table = \Bespari\Support\Helpers::table( 'channel_settlement_periods' );

		$wpdb->delete( $table, array( 'channel_id' => $channel_id ) );

		$now = \Bespari\Support\Helpers::now();
		foreach ( $periods as $i => $p ) {
			if ( empty( $p['title'] ) ) {
				continue;
			}
			$wpdb->insert( $table, array(
				'channel_id'       => $channel_id,
				'title'            => sanitize_text_field( $p['title'] ),
				'start_day'        => (int) ( $p['start_day'] ?? 1 ),
				'end_day'          => (int) ( $p['end_day'] ?? 31 ),
				'payment_due_days' => (int) ( $p['payment_due_days'] ?? 0 ),
				'is_active'        => 1,
				'sort_order'       => $i,
				'created_at'       => $now,
			) );
		}
	}
}
