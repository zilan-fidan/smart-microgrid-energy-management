<?php

namespace Tests\Unit\Decision\Rules;

use App\Domain\Decision\DecisionAction;
use App\Services\Decision\Rules\SocLimitGuardRule;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;
use Tests\Support\DecisionContextFactory;

class SocLimitGuardRuleTest extends TestCase
{
    public function test_applies_when_surplus_and_soc_at_max(): void
    {
        $rule = new SocLimitGuardRule();
        $battery = BatteryFactory::make(['soc_percent' => 90.0, 'max_soc' => 90.0]);
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, battery: $battery);

        $this->assertTrue($rule->applies($context));
    }

    public function test_applies_when_deficit_and_soc_at_min(): void
    {
        $rule = new SocLimitGuardRule();
        $battery = BatteryFactory::make(['soc_percent' => 10.0, 'min_soc' => 10.0]);
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, battery: $battery);

        $this->assertTrue($rule->applies($context));
    }

    public function test_does_not_apply_when_surplus_and_soc_has_headroom(): void
    {
        $rule = new SocLimitGuardRule();
        $battery = BatteryFactory::make(['soc_percent' => 50.0, 'max_soc' => 90.0]);
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, battery: $battery);

        $this->assertFalse($rule->applies($context));
    }

    public function test_does_not_apply_when_deficit_and_soc_above_min(): void
    {
        $rule = new SocLimitGuardRule();
        $battery = BatteryFactory::make(['soc_percent' => 50.0, 'min_soc' => 10.0]);
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, battery: $battery);

        $this->assertFalse($rule->applies($context));
    }

    public function test_does_not_apply_when_no_surplus_or_deficit(): void
    {
        $rule = new SocLimitGuardRule();
        $battery = BatteryFactory::make(['soc_percent' => 90.0, 'max_soc' => 90.0]);
        $context = DecisionContextFactory::make(productionKwh: 50.0, consumptionKwh: 50.0, battery: $battery);

        $this->assertFalse($rule->applies($context));
    }

    public function test_decide_redirects_overflow_to_sell_without_changing_soc(): void
    {
        $rule = new SocLimitGuardRule();
        $battery = BatteryFactory::make(['soc_percent' => 90.0, 'max_soc' => 90.0]);
        $context = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0, battery: $battery);

        $decision = $rule->decide($context);

        $this->assertSame(DecisionAction::Sell, $decision->action);
        $this->assertSame(20.0, $decision->amountKwh);
        $this->assertSame(90.0, $decision->resultingSocPercent);
        $this->assertSame(0.0, $decision->lossKwh);
        $this->assertNotEmpty($decision->reasons);
    }

    public function test_decide_redirects_underflow_to_draw_from_grid_without_changing_soc(): void
    {
        $rule = new SocLimitGuardRule();
        $battery = BatteryFactory::make(['soc_percent' => 10.0, 'min_soc' => 10.0]);
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, battery: $battery);

        $decision = $rule->decide($context);

        $this->assertSame(DecisionAction::DrawFromGrid, $decision->action);
        $this->assertSame(20.0, $decision->amountKwh);
        $this->assertSame(10.0, $decision->resultingSocPercent);
        $this->assertSame(0.0, $decision->lossKwh);
        $this->assertNotEmpty($decision->reasons);
    }
}
