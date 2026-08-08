<?php

namespace Tests\Unit\Decision\Rules;

use App\Domain\Decision\DecisionAction;
use App\Services\Decision\Rules\SellSurplusRule;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;
use Tests\Support\DecisionContextFactory;

class SellSurplusRuleTest extends TestCase
{
    public function test_applies_when_surplus_and_price_high(): void
    {
        $rule = new SellSurplusRule();
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 3.0);

        $this->assertTrue($rule->applies($context));
    }

    public function test_does_not_apply_when_surplus_and_price_low(): void
    {
        $rule = new SellSurplusRule();
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 1.0);

        $this->assertFalse($rule->applies($context));
    }

    public function test_does_not_apply_when_deficit_even_with_high_price(): void
    {
        $rule = new SellSurplusRule();
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, priceKwh: 3.0);

        $this->assertFalse($rule->applies($context));
    }

    public function test_decide_sells_full_surplus_without_touching_soc(): void
    {
        $rule = new SellSurplusRule();
        $battery = BatteryFactory::make(['soc_percent' => 50.0]);
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, priceKwh: 3.0, battery: $battery);

        $decision = $rule->decide($context);

        $this->assertSame(DecisionAction::Sell, $decision->action);
        $this->assertSame(20.0, $decision->amountKwh);
        $this->assertSame(50.0, $decision->resultingSocPercent);
        $this->assertSame(0.0, $decision->lossKwh);
        $this->assertStringContainsString('yüksek', mb_strtolower(implode(' ', $decision->reasons)));
    }
}
