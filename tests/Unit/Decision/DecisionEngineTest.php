<?php

namespace Tests\Unit\Decision;

use App\Domain\Decision\DecisionAction;
use App\Services\Decision\DecisionEngine;
use App\Services\Decision\Rules\DrawFromGridRule;
use App\Services\Decision\Rules\SellSurplusRule;
use App\Services\Decision\Rules\SocLimitGuardRule;
use App\Services\Decision\Rules\StoreSurplusRule;
use App\Services\Decision\Rules\UseBatteryRule;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;
use Tests\Support\DecisionContextFactory;

/**
 * Reproduces the 4 scenarios manually verified via tinker in Faz 3/6:
 * https://internal — Store, UseBattery, and the two SocLimitGuardRule
 * boundary cases. Prices mirror the exact array used during manual
 * verification (flat 2.0 with two overrides) so amounts/SOC match
 * bit-for-bit, not just "close enough".
 */
class DecisionEngineTest extends TestCase
{
    private function engine(): DecisionEngine
    {
        return new DecisionEngine([
            new SocLimitGuardRule(),
            new StoreSurplusRule(),
            new SellSurplusRule(),
            new UseBatteryRule(),
            new DrawFromGridRule(),
        ]);
    }

    private function verifiedPriceList(): array
    {
        $prices = array_fill(0, 24, 2.0);
        $prices[6] = 1.45;
        $prices[19] = 3.05;

        return $prices;
    }

    public function test_surplus_with_low_price_and_available_soc_stores_energy(): void
    {
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 55.0,
            'min_soc' => 10.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.9,
        ]);

        $context = DecisionContextFactory::make(
            hour: 6,
            productionKwh: 75.0,
            consumptionKwh: 50.0,
            priceKwh: 1.45,
            battery: $battery,
            hourlyPrices: $this->verifiedPriceList(),
        );

        $decision = $this->engine()->decide($context);

        $this->assertSame(DecisionAction::Store, $decision->action);
        $this->assertEqualsWithDelta(23.7171, $decision->amountKwh, 0.001);
        $this->assertEqualsWithDelta(78.72, $decision->resultingSocPercent, 0.01);
        $this->assertGreaterThan(0.0, $decision->lossKwh);

        $this->assertReasonsMention($decision->reasons, ['fazlas', 'fiyat', 'SOC']);
    }

    public function test_deficit_with_sufficient_soc_and_high_price_uses_battery_not_grid(): void
    {
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 80.0,
            'min_soc' => 10.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.9,
        ]);

        $context = DecisionContextFactory::make(
            hour: 19,
            productionKwh: 20.0,
            consumptionKwh: 55.0,
            priceKwh: 3.05,
            battery: $battery,
            hourlyPrices: $this->verifiedPriceList(),
        );

        $decision = $this->engine()->decide($context);

        $this->assertSame(DecisionAction::UseBattery, $decision->action);
        $this->assertNotSame(DecisionAction::Sell, $decision->action);
        $this->assertNotSame(DecisionAction::DrawFromGrid, $decision->action);

        $this->assertEqualsWithDelta(35.0, $decision->amountKwh, 0.001);
        $this->assertEqualsWithDelta(43.11, $decision->resultingSocPercent, 0.01);

        // Raw energy pulled from the battery is higher than what's delivered
        // to the load — the gap is the round-trip efficiency loss.
        $rawDrawKwh = $decision->amountKwh + $decision->lossKwh;
        $this->assertEqualsWithDelta(36.89, $rawDrawKwh, 0.01);
        $this->assertGreaterThan(0.0, $decision->lossKwh);

        $this->assertReasonsMention($decision->reasons, ['açığı', 'SOC', 'yüksek']);
    }

    public function test_guard_redirects_to_sell_when_soc_is_at_max_and_soc_is_unchanged(): void
    {
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 90.0,
            'min_soc' => 10.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.9,
        ]);

        $context = DecisionContextFactory::make(
            hour: 6,
            productionKwh: 75.0,
            consumptionKwh: 50.0,
            priceKwh: 1.45,
            battery: $battery,
            hourlyPrices: $this->verifiedPriceList(),
        );

        $decision = $this->engine()->decide($context);

        $this->assertSame(DecisionAction::Sell, $decision->action);
        $this->assertNotSame(DecisionAction::Store, $decision->action);
        $this->assertEqualsWithDelta(25.0, $decision->amountKwh, 0.001);
        $this->assertSame(90.0, $decision->resultingSocPercent);
        $this->assertSame($battery->getSocPercent(), $decision->resultingSocPercent);

        $this->assertReasonsMention($decision->reasons, ['SOC', 'engellendi']);
    }

    public function test_guard_redirects_to_draw_from_grid_when_soc_is_at_min_and_soc_is_unchanged(): void
    {
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 10.0,
            'min_soc' => 10.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.9,
        ]);

        $context = DecisionContextFactory::make(
            hour: 19,
            productionKwh: 20.0,
            consumptionKwh: 55.0,
            priceKwh: 3.05,
            battery: $battery,
            hourlyPrices: $this->verifiedPriceList(),
        );

        $decision = $this->engine()->decide($context);

        $this->assertSame(DecisionAction::DrawFromGrid, $decision->action);
        $this->assertNotSame(DecisionAction::UseBattery, $decision->action);
        $this->assertEqualsWithDelta(35.0, $decision->amountKwh, 0.001);
        $this->assertSame(10.0, $decision->resultingSocPercent);
        $this->assertSame($battery->getSocPercent(), $decision->resultingSocPercent);

        $this->assertReasonsMention($decision->reasons, ['SOC', 'engellendi']);
    }

    private function assertReasonsMention(array $reasons, array $needles): void
    {
        $this->assertNotEmpty($reasons);
        $haystack = mb_strtolower(implode(' | ', $reasons));

        foreach ($needles as $needle) {
            $this->assertStringContainsString(mb_strtolower($needle), $haystack, "reasons did not mention \"{$needle}\"");
        }
    }
}
