<?php

namespace Tests\Unit\Decision\Rules;

use App\Domain\Decision\DecisionAction;
use App\Services\Decision\Rules\StoreSurplusRule;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;
use Tests\Support\DecisionContextFactory;

class StoreSurplusRuleTest extends TestCase
{
    public function test_applies_when_surplus_and_price_low(): void
    {
        $rule = new StoreSurplusRule();
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 1.0);

        $this->assertTrue($rule->applies($context));
    }

    public function test_does_not_apply_when_surplus_and_price_high(): void
    {
        $rule = new StoreSurplusRule();
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 3.0);

        $this->assertFalse($rule->applies($context));
    }

    public function test_does_not_apply_when_deficit_even_with_low_price(): void
    {
        $rule = new StoreSurplusRule();
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, priceKwh: 1.0);

        $this->assertFalse($rule->applies($context));
    }

    /**
     * Mentor recommendation #1: a below-median price is no longer enough on
     * its own — storing must also clear the round-trip breakeven test. Here
     * the current price (1.0) is below the day's median (2.0), so the old
     * isPriceLow() check would have stored, but every remaining hour is
     * priced even lower (0.5) — discounted by efficiency, storing now would
     * be sold later for less than it cost, a guaranteed loss.
     */
    public function test_does_not_apply_when_breakeven_fails_even_at_a_below_median_price(): void
    {
        $rule = new StoreSurplusRule();

        $hourlyPrices = array_fill(0, 24, 2.0);
        $hourlyPrices[20] = 1.0;
        $hourlyPrices[21] = 0.5;
        $hourlyPrices[22] = 0.5;
        $hourlyPrices[23] = 0.5;

        $context = DecisionContextFactory::make(
            hour: 20,
            productionKwh: 70.0,
            consumptionKwh: 50.0,
            priceKwh: 1.0,
            hourlyPrices: $hourlyPrices,
        );

        $this->assertTrue($context->isPriceLow(), 'sanity check: price should read as low against the day median');
        $this->assertFalse($rule->applies($context));
    }

    public function test_decide_stores_surplus_scaled_by_charge_efficiency(): void
    {
        $rule = new StoreSurplusRule();
        // efficiency 0.81 -> sqrt = 0.9 exactly, for clean arithmetic.
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 50.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.81,
        ]);
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 1.0, battery: $battery);

        $decision = $rule->decide($context);

        $this->assertSame(DecisionAction::Store, $decision->action);
        $this->assertEqualsWithDelta(18.0, $decision->amountKwh, 0.0001);
        $this->assertEqualsWithDelta(68.0, $decision->resultingSocPercent, 0.0001);
        $this->assertEqualsWithDelta(2.0, $decision->lossKwh, 0.0001);
        $this->assertSame(0.0, $decision->curtailedSoldKwh, 'no curtailment expected when headroom covers the full surplus');
    }

    public function test_decide_caps_stored_amount_and_sells_the_remaining_surplus_when_headroom_is_insufficient(): void
    {
        $rule = new StoreSurplusRule();
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 85.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.81,
        ]);
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 1.0, battery: $battery);

        $decision = $rule->decide($context);

        // headroom = 5 kWh, wanted = 20 * 0.9 = 18 kWh -> capped to headroom.
        $this->assertEqualsWithDelta(5.0, $decision->amountKwh, 0.0001);
        $this->assertEqualsWithDelta(90.0, $decision->resultingSocPercent, 0.0001);
        $this->assertStringContainsString('sınırlı', implode(' ', $decision->reasons));

        // Raw surplus consumed by the 5 kWh that did fit = 5 / 0.9 = 5.5556 kWh.
        // The rest of the 20 kWh surplus (14.4444 kWh) is curtailed onto the market.
        $this->assertEqualsWithDelta(14.4444, $decision->curtailedSoldKwh, 0.001);

        // The reason must be self-contained (both numbers spelled out), since
        // it's the only place this split is explained to a demo viewer — the
        // "Miktar" column only shows the stored half of the story.
        $reasonText = implode(' ', $decision->reasons);
        $this->assertStringContainsString('piyasaya satıldı', $reasonText);
        $this->assertStringContainsString('5 kWh depolandı', $reasonText);
        $this->assertStringContainsString('14.44 kWh', $reasonText);
    }

    public function test_decide_computes_expected_profit_from_expected_sell_price_and_efficiency(): void
    {
        $rule = new StoreSurplusRule();
        // efficiency 0.81 (not sqrt-split) is the round-trip rate the profit
        // formula applies to the expected sell price.
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 50.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.81,
        ]);

        $hourlyPrices = array_fill(0, 24, 2.0);
        $hourlyPrices[10] = 1.0;
        $context = DecisionContextFactory::make(
            hour: 10,
            productionKwh: 70.0,
            consumptionKwh: 50.0,
            priceKwh: 1.0,
            battery: $battery,
            hourlyPrices: $hourlyPrices,
        );

        $decision = $rule->decide($context);

        // storedKwh = 18 (surplus 20 * sqrt(0.81) charge efficiency, headroom not limiting).
        // expectedSellPrice = 75th percentile of the remaining flat-2.0 hours = 2.0.
        // expectedProfitTl = (2.0 * 0.81 - 1.0) * 18 = 0.62 * 18 = 11.16 TL.
        $this->assertEqualsWithDelta(18.0, $decision->amountKwh, 0.0001);
        $this->assertEqualsWithDelta(11.16, $decision->expectedProfitTl, 0.001);
        $this->assertStringContainsString('beklenen kârı ≈ 11.16 TL', implode(' ', $decision->reasons));
    }
}
