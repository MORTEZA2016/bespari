<?php
/**
 * موتور مالی — جمع‌زنی چندآیتمی روی اسنپ‌شات PricingEngine.
 *
 * فقط برای سفارش‌های جدید محاسبه زنده می‌کند؛
 * سفارش‌های قدیمی از pricing_snapshot / financials_snapshot می‌خوانند.
 *
 * @package Bespari\Modules\Finance
 */

namespace Bespari\Modules\Finance;

use Bespari\Modules\Channel\ChannelRepository;
use Bespari\Modules\Pricing\PricingEngine;
use Bespari\Modules\Product\ProductRepository;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Calculator {

	private PricingEngine $engine;

	public function __construct() {
		$this->engine = new PricingEngine();
	}

	/**
	 * محاسبه مالی سفارش از روی آیتم‌ها (هر آیتم: product_id, quantity).
	 *
	 * @param int    $channel_id کانال.
	 * @param string $sale_type  نوع فروش.
	 * @param int    $seller_id  بازاریاب.
	 * @param array  $items      [ ['product_id'=>int,'quantity'=>int], ... ]
	 * @return array {gross, deductions, net, profit, rule_version, items_breakdown, pricing_snapshot, financials_snapshot}
	 */
	public function calculate_order( int $channel_id, string $sale_type, int $seller_id, array $items ): array {
		$channel_repo = new ChannelRepository();
		$product_repo = new ProductRepository();

		$channel = $channel_repo->get_by_id( $channel_id );
		if ( ! $channel ) {
			return $this->empty_result();
		}

		$gross       = 0.0;
		$deductions  = 0.0;
		$net         = 0.0;
		$profit      = 0.0;
		$breakdown   = array();
		$version     = $this->engine->current_rule_version();

		foreach ( $items as $row ) {
			$product_id = (int) ( $row['product_id'] ?? 0 );
			$qty        = max( 1, (int) ( $row['quantity'] ?? 1 ) );

			$product = $product_repo->get_full( $product_id );
			if ( ! $product ) {
				continue;
			}

			$calc = $this->engine->calculate( $product, $channel, $sale_type, $seller_id, $qty );
			$f    = $calc['financials'];

			// قیمت دستی (اگر کاربر قیمت فروش واقعی را override کرد — مثلا تخفیف کانال).
			$override = isset( $row['unit_price_override'] ) ? (float) $row['unit_price_override'] : 0;
			$unit     = (float) $calc['sale_price_unit'];
			$line     = (float) $calc['sale_price_total'];
			$ded_unit = (float) $f['total_deductions'];
			$net_unit = (float) $f['net_receivable'];
			$prof_unit = (float) $f['profit'];

			if ( $override > 0 && abs( $override - $unit ) > 0.001 ) {
				$unit      = $override;
				$line      = round( $override * $qty, 2 );
				$net_unit  = round( $override - $ded_unit, 2 );
				$prof_unit = round( $net_unit - (float) $f['product_cost'], 2 );
			}

			$gross      += $line;
			$deductions += $ded_unit * $qty;
			$net        += $net_unit * $qty;
			$profit     += $prof_unit * $qty;

			$breakdown[] = array(
				'product_id'      => $product_id,
				'product_name'    => $product->name,
				'sku'             => $product->sku,
				'quantity'        => $qty,
				'base_price'      => $calc['base_price'],
				'unit_price'      => $unit,
				'line_total'      => $line,
				'cost'            => (float) $f['product_cost'],
				'financials'      => $f,
				'applied_rules'   => $calc['applied_rules'],
				'rule_version'    => $calc['rule_version'],
			);
		}

		$pricing_snapshot    = array(
			'channel_id'   => $channel_id,
			'sale_type'    => $sale_type,
			'seller_id'    => $seller_id,
			'rule_version' => $version,
			'items'        => $breakdown,
		);

		$financials_snapshot = array(
			'gross'      => $gross,
			'deductions' => $deductions,
			'net'        => $net,
			'profit'     => $profit,
		);

		return array(
			'gross'               => $gross,
			'deductions'          => $deductions,
			'net'                 => $net,
			'profit'              => $profit,
			'rule_version'        => $version,
			'items_breakdown'     => $breakdown,
			'pricing_snapshot'    => $pricing_snapshot,
			'financials_snapshot' => $financials_snapshot,
		);
	}

	private function empty_result(): array {
		return array(
			'gross'               => 0,
			'deductions'          => 0,
			'net'                 => 0,
			'profit'              => 0,
			'rule_version'        => 1,
			'items_breakdown'     => array(),
			'pricing_snapshot'    => array(),
			'financials_snapshot' => array(),
		);
	}
}
