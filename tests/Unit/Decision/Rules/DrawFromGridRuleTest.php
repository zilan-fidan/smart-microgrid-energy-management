<?php

namespace Tests\Unit\Decision\Rules;

use App\Domain\Decision\DecisionAction;
use App\Services\Decision\Rules\DrawFromGridRule;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;
use Tests\Support\DecisionContextFactory;

class DrawFromGridRuleTest extends TestCase
{
    public function test_applies_always_returns_true(): void
    {
        $rule = new DrawFromGridRule();

        $deficitContext = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0);
        $surplusContext = DecisionContextFactory::make(productionKwh: 70.0, consumptionKwh: 50.0);
        $balancedContext = DecisionContextFactory::make(productionKwh: 50.0, consumptionKwh: 50.0);

        $this->assertTrue($rule->applies($deficitContext));
        $this->assertTrue($rule->applies($surplusContext));
        $this->assertTrue($rule->applies($balancedContext));
    }

    public function test_decide_draws_the_full_deficit_from_grid(): void
    {
        $rule = new DrawFromGridRule();
        $battery = BatteryFactory::make(['soc_percent' => 40.0]);
        $context = DecisionContextFactory::make(productionKwh: 30.0, consumptionKwh: 50.0, battery: $battery);

        $decision = $rule->decide($context);

        $this->assertSame(DecisionAction::DrawFromGrid, $decision->action);
        $this->assertSame(20.0, $decision->amountKwh);
        $this->assertSame(40.0, $decision->resultingSocPercent);
        $this->assertSame(0.0, $decision->lossKwh);
        $this->assertStringContainsString('şebekeden', mb_strtolower(implode(' ', $decision->reasons)));
    }

    public function test_decide_reports_balanced_when_production_matches_consumption(): void
    {
        $rule = new DrawFromGridRule();
        $context = DecisionContextFactory::make(productionKwh: 50.0, consumptionKwh: 50.0);

        $decision = $rule->decide($context);

        $this->assertSame(DecisionAction::DrawFromGrid, $decision->action);
        $this->assertSame(0.0, $decision->amountKwh);
        $this->assertStringContainsString('dengede', mb_strtolower(implode(' ', $decision->reasons)));
    }
}
