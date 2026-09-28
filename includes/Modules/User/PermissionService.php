<?php
/**
 * سیستم دسترسی قابل توسعه‌ی بسپاری.
 *
 * دو نوع سوژه دسترسی تعریف می‌شود:
 *  1. منوها (menu subjects) — هر منوی پنل با یکی از حالت‌های none/read/edit.
 *  2. موجودیت‌ها (entity subjects) — دسترسی به زیرمجموعه‌ای از داده‌ها
 *     (کانال‌های فروش، برندها، دسته‌بندی‌ها، محصولات) به‌صورت لیست شناسه مجاز.
 *
 * ذخیره‌سازی: usermeta با کلید `bespari_permissions`.
 *
 * قواعد:
 *  - کاربر دارای cap `bespari_manage_users` (مدیر) دسترسی کامل دارد.
 *  - کاربر بدون grant صریح به رفتار نقش (caps) برمی‌گردد — backward compatible.
 *  - grant صریح روی یک منو، ملاک نهایی است (none = بدون دسترسی).
 *
 * @package Bespari\Modules\User
 */

namespace Bespari\Modules\User;

use Bespari\Api\AppAuth;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PermissionService {

	public const META_KEY = 'bespari_permissions';

	public const MODE_NONE = 'none';
	public const MODE_READ = 'read';
	public const MODE_EDIT = 'edit';

	/**
	 * گروه‌بندی سوژه‌های موجودیت برای نمایش در فرم.
	 *
	 * @return array key => [label, description]
	 */
	public static function entity_subjects(): array {
		$entities = array(
			'channels'   => array( __( 'کانال‌های فروش', 'bespari-core' ), __( 'دیدن سفارشات و داده‌ی مربوط به این کانال‌ها', 'bespari-core' ) ),
			'brands'     => array( __( 'برندها', 'bespari-core' ), __( 'دیدن برندها و داده‌ی وابسته به آن‌ها', 'bespari-core' ) ),
			'categories' => array( __( 'دسته‌بندی‌ها', 'bespari-core' ), __( 'دیدن دسته‌بندی‌های مجاز', 'bespari-core' ) ),
			'products'   => array( __( 'محصولات', 'bespari-core' ), __( 'دیدن محصولات مجاز', 'bespari-core' ) ),
		);

		/**
		 * افزودن موجودیت‌های جدید به سیستم دسترسی.
		 *
		 * @param array $entities key => [label, description]
		 */
		return apply_filters( 'bespari_permission_entities', $entities );
	}

	/**
	 * حالت‌های ممکن برای دسترسی به یک منو.
	 *
	 * @return array mode => label
	 */
	public static function modes(): array {
		return array(
			self::MODE_NONE => __( 'بدون دسترسی', 'bespari-core' ),
			self::MODE_READ => __( 'خواندن', 'bespari-core' ),
			self::MODE_EDIT => __( 'ویرایش', 'bespari-core' ),
		);
	}

	/**
	 * لیست منوها به‌عنوان سوژه دسترسی — همگام با AppAuth::menus().
	 *
	 * @return array key => label
	 */
	public static function menu_subjects(): array {
		$out = array();

		foreach ( AppAuth::menus() as $key => $item ) {
			$out[ $key ] = $item[0];
		}

		return $out;
	}

	/**
	 * گزینه‌های قابل انتخاب برای یک موجودیت (برای فرم دسترسی).
	 *
	 * @param string $entity کلید موجودیت.
	 * @return array [{value, label}]
	 */
	public static function entity_options( string $entity ): array {
		$options = array();

		switch ( $entity ) {
			case 'channels':
				foreach ( ( new \Bespari\Modules\Channel\ChannelService() )->list() as $ch ) {
					$options[] = array( 'value' => (int) $ch->id, 'label' => $ch->name );
				}
				break;

			case 'brands':
				foreach ( ( new \Bespari\Modules\Brand\BrandRepository() )->get_all() as $b ) {
					$options[] = array( 'value' => (int) $b->id, 'label' => $b->name );
				}
				break;

			case 'products':
				$result = ( new \Bespari\Modules\Product\ProductRepository() )->paginate( array( 'per_page' => 200 ) );
				foreach ( $result['items'] as $p ) {
					$options[] = array( 'value' => (int) $p->id, 'label' => $p->name . ( $p->sku ? ' (' . $p->sku . ')' : '' ) );
				}
				break;

			case 'categories':
				// دسته‌بندی‌ها از WooCommerce می‌آیند (ماژول دسته‌بندی‌ها هنوز پیاده‌سازی نشده).
				if ( \Bespari\Support\Helpers::woocommerce_is_active() ) {
					$terms = get_terms( array(
						'taxonomy'   => 'product_cat',
						'hide_empty' => false,
						'number'     => 200,
					) );

					if ( ! is_wp_error( $terms ) ) {
						foreach ( $terms as $t ) {
							$options[] = array( 'value' => (int) $t->term_id, 'label' => $t->name );
						}
					}
				}
				break;

			default:
				/**
				 * تأمین گزینه‌های یک موجودیت سفارشی.
				 *
				 * @param array  $options [{value, label}]
				 * @param string $entity
				 */
				$options = apply_filters( 'bespari_permission_entity_options', $options, $entity );
		}

		return $options;
	}

	/**
	 * آیا کاربر مدیر (دسترسی کامل) است؟
	 *
	 * @param \WP_User $user کاربر.
	 */
	public static function is_admin( \WP_User $user ): bool {
		return $user->exists() && $user->has_cap( 'bespari_manage_users' );
	}

	/**
	 * خواندن grant های ذخیره‌شده‌ی کاربر.
	 *
	 * @param int $user_id شناسه کاربر.
	 * @return array {menus: array, entities: array, channel_view: bool}
	 */
	public static function get_grants( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY, true );

		if ( ! is_array( $raw ) ) {
			return array( 'menus' => array(), 'entities' => array(), 'channel_view' => true );
		}

		return array(
			'menus'        => is_array( $raw['menus'] ?? null ) ? $raw['menus'] : array(),
			'entities'     => is_array( $raw['entities'] ?? null ) ? $raw['entities'] : array(),
			'channel_view' => isset( $raw['channel_view'] ) ? (bool) $raw['channel_view'] : true,
		);
	}

	/**
	 * ذخیره‌سازی grant های کاربر (با اعتبارسنجی کامل).
	 *
	 * @param int   $user_id شناسه کاربر.
	 * @param array $raw    ورودی خام.
	 */
	public static function set_grants( int $user_id, array $raw ): void {
		$valid_menus   = self::menu_subjects();
		$valid_modes   = array_keys( self::modes() );
		$valid_ents    = array_keys( self::entity_subjects() );

		$menus = array();
		foreach ( ( is_array( $raw['menus'] ?? null ) ? $raw['menus'] : array() ) as $key => $mode ) {
			$key = sanitize_key( (string) $key );

			if ( isset( $valid_menus[ $key ] ) && in_array( $mode, $valid_modes, true ) ) {
				$menus[ $key ] = $mode;
			}
		}

		$entities = array();
		foreach ( ( is_array( $raw['entities'] ?? null ) ? $raw['entities'] : array() ) as $entity => $ids ) {
			$entity = sanitize_key( (string) $entity );

			if ( ! in_array( $entity, $valid_ents, true ) ) {
				continue;
			}

			$clean = array();
			foreach ( (array) $ids as $id ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					$clean[] = $id;
				}
			}

			$entities[ $entity ] = array_values( array_unique( $clean ) );
		}

		update_user_meta( $user_id, self::META_KEY, array(
			'menus'        => $menus,
			'entities'     => $entities,
			'channel_view' => isset( $raw['channel_view'] ) ? (bool) $raw['channel_view'] : true,
		) );
	}

	/**
	 * حذف همه‌ی grant های کاربر (برگرداندن به پیش‌فرض نقش).
	 *
	 * @param int $user_id شناسه کاربر.
	 */
	public static function clear_grants( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * حالت دسترسی کاربر به یک منو.
	 *
	 * @param \WP_User $user کاربر.
	 * @param string   $menu کلید منو.
	 * @return string none|read|edit
	 */
	public static function menu_mode( \WP_User $user, string $menu ): string {
		if ( self::is_admin( $user ) ) {
			return self::MODE_EDIT;
		}

		$menus = AppAuth::menus();

		if ( ! isset( $menus[ $menu ] ) ) {
			return self::MODE_NONE;
		}

		$grants = self::get_grants( (int) $user->ID );

		if ( isset( $grants['menus'][ $menu ] ) ) {
			$mode = $grants['menus'][ $menu ];
			return in_array( $mode, array( self::MODE_READ, self::MODE_EDIT ), true ) ? $mode : self::MODE_NONE;
		}

		// بدون grant صریح → رفتار نقش (backward compatible: دسترسی کامل).
		return AppAuth::user_can( $user, $menus[ $menu ][1] ) ? self::MODE_EDIT : self::MODE_NONE;
	}

	/**
	 * آیا کاربر به منو با حالت خواسته‌شده دسترسی دارد؟
	 *
	 * @param \WP_User $user  کاربر.
	 * @param string   $menu  کلید منو.
	 * @param string   $mode  read یا edit.
	 */
	public static function can_menu( \WP_User $user, string $menu, string $mode = self::MODE_READ ): bool {
		$current = self::menu_mode( $user, $menu );

		if ( self::MODE_EDIT === $mode ) {
			return self::MODE_EDIT === $current;
		}

		return in_array( $current, array( self::MODE_READ, self::MODE_EDIT ), true );
	}

	/**
	 * شناسه‌های مجاز کاربر برای یک موجودیت.
	 *
	 * @param \WP_User $user   کاربر.
	 * @param string   $entity کلید موجودیت.
	 * @return int[]|null آرایه شناسه‌ها، یا null وقتی محدودیتی وجود ندارد.
	 */
	public static function allowed_ids( \WP_User $user, string $entity ): ?array {
		if ( self::is_admin( $user ) ) {
			return null;
		}

		if ( ! isset( self::entity_subjects()[ $entity ] ) ) {
			return null;
		}

		$grants = self::get_grants( (int) $user->ID );

		if ( ! isset( $grants['entities'][ $entity ] ) ) {
			return null;
		}

		return array_map( 'intval', $grants['entities'][ $entity ] );
	}

	/**
	 * آیا کاربر به یک رکورد از یک موجودیت دسترسی دارد؟
	 *
	 * @param \WP_User $user   کاربر.
	 * @param string   $entity کلید موجودیت.
	 * @param int      $id     شناسه رکورد.
	 */
	public static function can_entity( \WP_User $user, string $entity, int $id ): bool {
		$ids = self::allowed_ids( $user, $entity );

		if ( null === $ids ) {
			return true;
		}

		return in_array( $id, $ids, true );
	}

	/**
	 * آیا کاربر مجاز به دیدن کانال‌های فروش است؟
	 *
	 * @param \WP_User $user کاربر.
	 */
	public static function can_view_channels( \WP_User $user ): bool {
		if ( self::is_admin( $user ) ) {
			return true;
		}

		return (bool) ( self::get_grants( (int) $user->ID )['channel_view'] ?? true );
	}

	/**
	 * خلاصه‌ی دسترسی‌های کاربر برای نمایش در لیست کارمندان.
	 *
	 * @param int $user_id شناسه کاربر.
	 */
	public static function grants_summary( int $user_id ): string {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return '—';
		}

		if ( self::is_admin( $user ) ) {
			return __( 'دسترسی کامل', 'bespari-core' );
		}

		$grants = self::get_grants( $user_id );
		$menus  = is_array( $grants['menus'] ) ? $grants['menus'] : array();
		$count  = 0;

		foreach ( $menus as $mode ) {
			if ( self::MODE_NONE !== $mode ) {
				++$count;
			}
		}

		if ( 0 === $count ) {
			return __( 'پیش‌فرض نقش', 'bespari-core' );
		}

		/* translators: %d تعداد منوهای مجاز */
		return sprintf( __( '%d منوی مجاز', 'bespari-core' ), $count );
	}
}
