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
    }

    public function test_decide_caps_stored_amount_to_available_headroom(): void
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
    }
}
