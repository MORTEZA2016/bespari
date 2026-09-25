<?php
/**
 * Repository قوانین قیمت‌گذاری.
 *
 * @package Bespari\Modules\Pricing
 */

namespace Bespari\Modules\Pricing;

use Bespari\Support\BaseRepository;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PricingRepository extends BaseRepository {

	protected string $table       = 'pricing_rules';
	protected string $model_class = PricingRuleModel::class;

	/**
	 * دریافت همه قوانین مرتب‌شده بر اساس اولویت.
	 *
	 * @param bool $only_active فقط فعال.
	 * @return PricingRuleModel[]
	 */
	public function get_all_ordered( bool $only_active = false ): array {
		$where = $only_active ? array( 'is_active' => 1 ) : array();
		$rows  = $this->all( $where, 'priority', 'ASC' );

		return $this->hydrate( $rows );
	}

	/**
	 * دریافت یک قانون.
	 *
	 * @param int $id آی‌دی.
	 * @return PricingRuleModel|null
	 */
	public function get_by_id( int $id ): ?PricingRuleModel {
		$row = $this->find( $id );

		return $row ? PricingRuleModel::from_row( $row ) : null;
	}

	/**
	 * قوانین قابل اعمال برای یک محصول/کانال/نوع فروش (فقط فعال و معتبر زمانی).
	 *
	 * @param int    $channel_id آی‌دی کانال (0 = همه).
	 * @param int    $brand_id   آی‌دی برند.
	 * @param int    $category_id آی‌دی دسته.
	 * @param int    $product_id  آی‌دی محصول.
	 * @param int    $seller_id   آی‌دی بازاریاب.
	 * @param string $sale_type   cash|credit|all.
	 * @return PricingRuleModel[]
	 */
	public function applicable_rules(
		int $channel_id = 0,
		int $brand_id = 0,
		int $category_id = 0,
		int $product_id = 0,
		int $seller_id = 0,
		string $sale_type = 'all'
	): array {
		global $wpdb;

		$table = $this->table();

		$sql = "SELECT * FROM {$table}
				WHERE is_active = 1
				AND (sale_type = 'all' OR sale_type = %s)
				ORDER BY priority ASC, id ASC";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $sale_type ) ); // phpcs:ignore

		if ( ! $rows ) {
			return array();
		}

		$filtered = array();

		foreach ( $rows as $row ) {
			$model = PricingRuleModel::from_row( $row );

			if ( ! $model->is_in_valid_period() ) {
				continue;
			}

			// اگر قانون کانال خاص دارد و کانال انتخابی با آن همخوانی ندارد — حذف.
			if ( $model->channel_id > 0 && $model->channel_id !== $channel_id ) {
				continue;
			}

			// بررسی scope.
			$matches = false;
			switch ( $model->scope ) {
				case 'channel':
					$matches = ( 0 === $model->scope_id || $model->scope_id === $channel_id );
					break;
				case 'brand':
					$matches = ( 0 === $model->scope_id || $model->scope_id === $brand_id );
					break;
				case 'category':
					$matches = ( 0 === $model->scope_id || $model->scope_id === $category_id );
					break;
				case 'product':
					$matches = ( 0 === $model->scope_id || $model->scope_id === $product_id );
					break;
				case 'seller':
					$matches = ( 0 === $model->scope_id || $model->scope_id === $seller_id );
					break;
				default:
					$matches = true;
			}

			if ( $matches ) {
				$filtered[] = $model;
			}
		}

		return $filtered;
	}
}
