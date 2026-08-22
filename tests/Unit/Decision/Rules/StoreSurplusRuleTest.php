<?php

namespace Tests\Unit\Decision\Rules;

use App\Domain\Decision\DecisionAction;
use App\Services\Battery\DegradationCostCalculator;
use App\Services\Decision\Rules\StoreSurplusRule;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;
use Tests\Support\DecisionContextFactory;

class StoreSurplusRuleTest extends TestCase
{
    private function rule(): StoreSurplusRule
    {
        return new StoreSurplusRule(new DegradationCostCalculator());
    }

    public function test_applies_when_surplus_and_price_low(): void
    {
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 1.0);

        $this->assertTrue($this->rule()->applies($context));
    }

    public function test_does_not_apply_when_surplus_and_price_high(): void
    {
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 3.0);

        $this->assertFalse($this->rule()->applies($context));
    }

    public function test_does_not_apply_when_deficit_even_with_low_price(): void
    {
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, priceKwh: 1.0);

        $this->assertFalse($this->rule()->applies($context));
    }

    /**
     * Mentor recommendation #1: a below-median price is no longer enough on
     * its own — storing must also clear the round-trip breakeven test. Here
     * the current price (1.0) is below the day's median (2.0), so the old
     * isPriceLow() check would have stored, but every remaining hour is
     * priced even lower (0.5) — discounted by efficiency, storing now would
     * be sold later for less than it cost, a guaranteed loss even before
     * degradation cost is considered.
     */
    public function test_does_not_apply_when_breakeven_fails_even_at_a_below_median_price(): void
    {
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
        $this->assertFalse($this->rule()->applies($context));
    }

    /**
     * Mentor recommendation #2: clearing the round-trip efficiency spread is
     * no longer sufficient by itself — the spread must also outrun the
     * battery's amortized wear cost. Here the price spread (0.62 TL/kWh)
     * *would* have passed last turn's efficiency-only breakeven (spread > 0),
     * but a battery with a high replacement cost relative to its capacity
     * has a wear cost (2.5 TL/kWh) the spread doesn't come close to clearing.
     */
    public function test_does_not_apply_when_spread_clears_efficiency_but_not_degradation_cost(): void
    {
        // capacity 100 kWh, replacement 100,000 TL -> lifetime cycled kWh =
        // (100-80)/0.05 * 100 = 40,000 -> degradation cost = 100,000/40,000 = 2.5 TL/kWh.
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'efficiency_rate' => 0.9,
            'replacement_cost_tl' => 100_000.0,
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

        // Sanity check: this spread alone (before degradation cost) would have
        // cleared last turn's breakeven test (expectedSellPrice * efficiency > currentPrice).
        $this->assertGreaterThan($context->priceKwh, $context->getExpectedSellPrice() * $battery->getEfficiencyRate());

        $this->assertFalse($this->rule()->applies($context));
    }

    public function test_decide_stores_surplus_scaled_by_charge_efficiency(): void
    {
        // efficiency 0.81 -> sqrt = 0.9 exactly, for clean arithmetic.
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 50.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.81,
        ]);
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 1.0, battery: $battery);

        $decision = $this->rule()->decide($context);

        $this->assertSame(DecisionAction::Store, $decision->action);
        $this->assertEqualsWithDelta(18.0, $decision->amountKwh, 0.0001);
        $this->assertEqualsWithDelta(68.0, $decision->resultingSocPercent, 0.0001);
        $this->assertEqualsWithDelta(2.0, $decision->lossKwh, 0.0001);
        $this->assertSame(0.0, $decision->curtailedSoldKwh, 'no curtailment expected when headroom covers the full surplus');
    }

    public function test_decide_caps_stored_amount_and_sells_the_remaining_surplus_when_headroom_is_insufficient(): void
    {
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 85.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.81,
        ]);
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 1.0, battery: $battery);

        $decision = $this->rule()->decide($context);

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

    public function test_decide_computes_expected_profit_net_of_degradation_cost(): void
    {
        // efficiency 0.81 (not sqrt-split) is the round-trip rate the profit
        // formula applies to the expected sell price. capacity 100 kWh with
        // BatteryFactory's default replacement cost (100 TL/kWh nameplate)
        // -> degradation cost = 100 * 100 / ((100-80)/0.05 * 100) = 0.25 TL/kWh.
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

        $decision = $this->rule()->decide($context);

        // storedKwh = 18 (surplus 20 * sqrt(0.81) charge efficiency, headroom not limiting).
        // expectedSellPrice = 75th percentile of the remaining flat-2.0 hours = 2.0.
        // expectedProfitTl = (2.0 * 0.81 - 1.0 - 0.25) * 18 = 0.37 * 18 = 6.66 TL.
        $this->assertEqualsWithDelta(18.0, $decision->amountKwh, 0.0001);
        $this->assertEqualsWithDelta(6.66, $decision->expectedProfitTl, 0.001);
        $this->assertStringContainsString('Beklenen net kâr ≈ 6.66 TL', implode(' ', $decision->reasons));
        $this->assertStringContainsString('aşınma maliyeti', implode(' ', $decision->reasons));
    }
}
