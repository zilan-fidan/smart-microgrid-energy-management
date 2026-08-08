<?php

namespace Tests\Unit\Decision\Rules;

use App\Domain\Decision\DecisionAction;
use App\Services\Decision\Rules\UseBatteryRule;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;
use Tests\Support\DecisionContextFactory;

class UseBatteryRuleTest extends TestCase
{
    public function test_applies_when_deficit_and_soc_above_min(): void
    {
        $rule = new UseBatteryRule();
        $battery = BatteryFactory::make(['soc_percent' => 50.0, 'min_soc' => 10.0]);
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, battery: $battery);

        $this->assertTrue($rule->applies($context));
    }

    public function test_does_not_apply_when_deficit_and_soc_at_min(): void
    {
        $rule = new UseBatteryRule();
        $battery = BatteryFactory::make(['soc_percent' => 10.0, 'min_soc' => 10.0]);
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, battery: $battery);

        $this->assertFalse($rule->applies($context));
    }

    public function test_does_not_apply_when_surplus(): void
    {
        $rule = new UseBatteryRule();
        $battery = BatteryFactory::make(['soc_percent' => 50.0, 'min_soc' => 10.0]);
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, battery: $battery);

        $this->assertFalse($rule->applies($context));
    }

    public function test_decide_delivers_deficit_scaled_by_discharge_efficiency(): void
    {
        $rule = new UseBatteryRule();
        // efficiency 0.81 -> sqrt = 0.9 exactly, for clean arithmetic.
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 50.0,
            'min_soc' => 10.0,
            'efficiency_rate' => 0.81,
        ]);
        $context = DecisionContextFactory::make(productionKwh: 32.0, consumptionKwh: 50.0, battery: $battery);

        $decision = $rule->decide($context);

        $this->assertSame(DecisionAction::UseBattery, $decision->action);
        $this->assertEqualsWithDelta(18.0, $decision->amountKwh, 0.0001);
        $this->assertEqualsWithDelta(30.0, $decision->resultingSocPercent, 0.0001);
        $this->assertEqualsWithDelta(2.0, $decision->lossKwh, 0.0001);
    }

    public function test_decide_caps_delivered_amount_to_dischargeable_capacity(): void
    {
        $rule = new UseBatteryRule();
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'soc_percent' => 15.0,
            'min_soc' => 10.0,
            'efficiency_rate' => 0.81,
        ]);
        $context = DecisionContextFactory::make(productionKwh: 32.0, consumptionKwh: 50.0, battery: $battery);

        $decision = $rule->decide($context);

        // dischargeable = 5 kWh, max deliverable = 5 * 0.9 = 4.5 kWh -> capped.
        $this->assertEqualsWithDelta(4.5, $decision->amountKwh, 0.0001);
        $this->assertEqualsWithDelta(10.0, $decision->resultingSocPercent, 0.0001);
        $this->assertStringContainsString('sınırlı', implode(' ', $decision->reasons));
    }

    public function test_decide_mentions_price_when_price_is_high(): void
    {
        $rule = new UseBatteryRule();
        $battery = BatteryFactory::make(['soc_percent' => 50.0, 'min_soc' => 10.0]);
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, priceKwh: 3.0, battery: $battery);

        $decision = $rule->decide($context);

        $this->assertStringContainsString('yüksek', mb_strtolower(implode(' ', $decision->reasons)));
    }
}
