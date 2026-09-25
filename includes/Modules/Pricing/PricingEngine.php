<?php
/**
 * موتور قیمت‌گذاری — اعمال قوانین اولویت‌دار و محاسبه قیمت نهایی هر کانال.
 *
 * زنجیره محاسبه:
 *   قیمت پایه سایت
 *     + درصد افزایش کانال (price_markup)
 *     + قوانین قیمت‌گذاری (به ترتیب اولویت، stackable)
 *     + افزایش فروش اعتباری (credit_markup)
 *     = قیمت فروش
 *
 *   سپس:
 *   قیمت فروش - مالیات - پورسانت - کارمزد - حمل - پردازش - تبلیغات = خالص قابل دریافت
 *
 * @package Bespari\Modules\Pricing
 */

namespace Bespari\Modules\Pricing;

use Bespari\Modules\Channel\ChannelRepository;
use Bespari\Modules\Channel\ChannelModel;
use Bespari\Modules\Product\ProductModel;

// جلوگیری از دسترسی مستقیم.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PricingEngine {

	private PricingRepository $rules_repo;
	private ChannelRepository $channel_repo;

	public function __construct() {
		$this->rules_repo   = new PricingRepository();
		$this->channel_repo = new ChannelRepository();
	}

	/**
	 * محاسبه کامل قیمت و شکست مالی برای یک محصول در یک کانال.
	 *
	 * @param ProductModel $product   محصول.
	 * @param ChannelModel $channel   کانال فروش.
	 * @param string       $sale_type نوع فروش: cash|credit.
	 * @param int          $seller_id آی‌دی بازاریاب (اختیاری).
	 * @param int          $quantity  تعداد (برای محاسبه مقادیر کل).
	 * @return array ساختار محاسبات با کلیدهای تفکیکی.
	 */
	public function calculate(
		ProductModel $product,
		ChannelModel $channel,
		string $sale_type = 'cash',
		int $seller_id = 0,
		int $quantity = 1
	): array {
		$quantity = max( 1, $quantity );

		// ۱. شروع از قیمت پایه.
		$base_price = (float) $product->base_price;

		// ۲. درصد افزایش پایه کانال.
		$channel_markup      = $base_price * ( $channel->price_markup_percent / 100 );
		$after_channel       = $base_price + $channel_markup;

		// ۳. اعمال قوانین قیمت‌گذاری (به ترتیب اولویت).
		$rules           = $this->rules_repo->applicable_rules(
			$channel->id,
			$product->brand_id,
			$product->category_id,
			$product->id,
			$seller_id,
			$sale_type
		);
		$rules_total     = 0.0;
		$applied_rules   = array();
		$running_price   = $after_channel;
		$hit_non_stack   = false;

		foreach ( $rules as $rule ) {
			if ( $hit_non_stack ) {
				// پس از برخورد به یک قانون غیرقابل‌تجمع، قوانین بعدی اعمال نمی‌شوند.
				break;
			}

			$amount = $this->apply_rule( $rule, $running_price, $base_price );

			$applied_rules[] = array(
				'rule_id' => $rule->id,
				'title'   => $rule->title,
				'amount'  => $amount,
				'type'    => $rule->adjustment_type,
			);

			if ( ! $rule->stackable ) {
				// قانون غیرقابل‌تجمع: قوانین قبلی را نادیده می‌گیرد و فقط همین اعمال می‌شود.
				$rules_total   = $amount;
				$running_price = $after_channel + $amount;
				$hit_non_stack = true;
				break;
			}

			$rules_total   += $amount;
			$running_price += $amount;
		}

		$after_rules = $after_channel + $rules_total;

		// ۴. افزایش فروش اعتباری (فقط برای نوع اعتباری).
		$credit_markup = 0.0;
		if ( 'credit' === $sale_type ) {
			$credit_markup = $after_rules * ( $channel->credit_markup_percent / 100 );
		}

		// ۵. قیمت فروش نهایی (واحد).
		$sale_price = $after_rules + $credit_markup;

		// ۶. محاسبات مالی — کسورات.
		$financials = $this->calculate_financials( $sale_price, $channel, $product );

		return array(
			'product_id'        => $product->id,
			'channel_id'        => $channel->id,
			'sale_type'         => $sale_type,
			'quantity'          => $quantity,
			'base_price'        => $base_price,
			'channel_markup'    => $channel_markup,
			'rules_total'       => $rules_total,
			'applied_rules'     => $applied_rules,
			'credit_markup'     => $credit_markup,
			'sale_price_unit'   => $sale_price,
			'sale_price_total'  => $sale_price * $quantity,
			'financials'        => $financials,
			'rule_version'      => $this->current_rule_version(),
		);
	}

	/**
	 * اعمال یک قانون روی قیمت جاری.
	 *
	 * @param PricingRuleModel $rule     قانون.
	 * @param float            $running  قیمت جاری.
	 * @param float            $base     قیمت پایه.
	 * @return float مقدار تعدیل.
	 */
	private function apply_rule( PricingRuleModel $rule, float $running, float $base ): float {
		if ( 'percent' === $rule->adjustment_type ) {
			// درصد روی قیمت جاری (مرکب) اعمال می‌شود.
			return $running * ( $rule->adjustment_value / 100 );
		}

		// مبلغ ثابت.
		return (float) $rule->adjustment_value;
	}

	/**
	 * محاسبه شکست مالی و کسورات.
	 *
	 * @param float        $sale_price قیمت فروش واحد.
	 * @param ChannelModel $channel    کانال.
	 * @param ProductModel $product    محصول.
	 * @return array
	 */
	private function calculate_financials( float $sale_price, ChannelModel $channel, ProductModel $product ): array {
		// مالیات.
		$tax = 0.0;
		if ( $channel->tax_percent > 0 ) {
			// inclusive: مالیات در قیمت لحاظ شده؛ exclusive: اضافه می‌شود.
			$tax = 'inclusive' === $channel->tax_mode
				? $sale_price - ( $sale_price / ( 1 + $channel->tax_percent / 100 ) )
				: $sale_price * ( $channel->tax_percent / 100 );
		}

		$commission    = $sale_price * ( $channel->commission_percent / 100 );
		$processing    = $sale_price * ( $channel->processing_fee_percent / 100 );
		$shipping      = $sale_price * ( $channel->shipping_fee_percent / 100 );
		$advertising   = $sale_price * ( $channel->advertising_fee_percent / 100 );
		$gateway       = $sale_price * ( $channel->gateway_fee_percent / 100 );
		$fixed_fee     = (float) $channel->fixed_fee;

		$total_deductions = $tax + $commission + $processing + $shipping + $advertising + $gateway + $fixed_fee;
		$net_receivable   = $sale_price - $total_deductions;

		$cost = $product->finished_cost > 0 ? $product->finished_cost : $product->purchase_price;
		$profit = $net_receivable - $cost;

		return array(
			'tax'            => $tax,
			'commission'     => $commission,
			'processing_fee' => $processing,
			'shipping_fee'   => $shipping,
			'advertising_fee' => $advertising,
			'gateway_fee'    => $gateway,
			'fixed_fee'      => $fixed_fee,
			'total_deductions' => $total_deductions,
			'net_receivable' => $net_receivable,
			'product_cost'   => $cost,
			'profit'         => $profit,
		);
	}

	/**
	 * نسخه فعلی قوانین (برای نسخه‌بندی و ثبات محاسبات قدیمی).
	 *
	 * @return int
	 */
	public function current_rule_version(): int {
		$version = (int) get_option( 'bespari_pricing_version', 1 );

		return $version ?: 1;
	}

	/**
	 * افزایش نسخه قوانین (هنگام تغییر قوانین).
	 */
	public function bump_version(): void {
		$version = $this->current_rule_version();
		update_option( 'bespari_pricing_version', $version + 1 );
	}

	/**
	 * محاسبه قیمت نهایی برای همه کانال‌های فعال یک محصول (برای پیش‌نمایش و سینک).
	 *
	 * @param ProductModel $product   محصول.
	 * @param string       $sale_type نوع فروش.
	 * @return array قیمت نهایی به تفکیک کانال [channel_id => array].
	 */
	public function calculate_all_channels( ProductModel $product, string $sale_type = 'cash' ): array {
		$result   = array();
		$channels = $this->channel_repo->get_all( true );

		foreach ( $channels as $channel ) {
			$result[ $channel->id ] = $this->calculate( $product, $channel, $sale_type );
		}

		return $result;
	}
}
