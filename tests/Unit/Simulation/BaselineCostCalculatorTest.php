<?php

namespace Tests\Unit\Simulation;

use App\Domain\Decision\DecisionAction;
use App\Domain\Decision\DecisionContext;
use App\Domain\Simulation\DailySimulation;
use App\Services\Decision\DecisionEngine;
use App\Services\Decision\Rules\DrawFromGridRule;
use App\Services\Decision\Rules\SellSurplusRule;
use App\Services\Decision\Rules\SocLimitGuardRule;
use App\Services\Decision\Rules\StoreSurplusRule;
use App\Services\Decision\Rules\UseBatteryRule;
use App\Services\Simulation\BaselineCostCalculator;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;
use Tests\Support\SimulationResultFactory;

/**
 * Reproduces the 4-hour scenario manually verified via tinker in Faz 6:
 * baseline (no-battery) net cost 35 TL vs. actual net cost -10 TL,
 * i.e. 45 TL of savings attributable to the battery.
 */
class BaselineCostCalculatorTest extends TestCase
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

    /**
     * h0: surplus 20 @1.5 (low)                       -> Store
     * h1: surplus 30 @3.0, battery already at max SOC -> guard -> Sell
     * h2: deficit 25 @3.0 (high), battery has headroom -> UseBattery
     * h3: deficit 40, battery already at min SOC       -> guard -> DrawFromGrid
     */
    private function buildFourHourSimulation(): array
    {
        $engine = $this->engine();

        $priceList = array_fill(0, 24, 2.0);
        $priceList[0] = 1.5;
        $priceList[1] = 3.0;
        $priceList[2] = 3.0;
        $priceList[3] = 2.0;

        $production = [0 => 45.0, 1 => 55.0, 2 => 15.0, 3 => 15.0];
        $consumption = [0 => 25.0, 1 => 25.0, 2 => 40.0, 3 => 55.0];

        $batteries = [
            0 => BatteryFactory::make(['soc_percent' => 50.0]),
            1 => BatteryFactory::make(['soc_percent' => 90.0]),
            2 => BatteryFactory::make(['soc_percent' => 50.0]),
            3 => BatteryFactory::make(['soc_percent' => 10.0]),
        ];

        $results = [];
        foreach ([0, 1, 2, 3] as $hour) {
            $context = new DecisionContext(
                $hour,
                $production[$hour],
                $consumption[$hour],
                $priceList[$hour],
                $batteries[$hour],
                $priceList,
            );

            $decision = $engine->decide($context);

            $results[] = SimulationResultFactory::make(
                hour: $hour,
                productionKwh: $production[$hour],
                consumptionKwh: $consumption[$hour],
                priceKwh: $priceList[$hour],
                decision: $decision,
                socPercentBefore: $batteries[$hour]->getSocPercent(),
            );
        }

        return [$results, $priceList];
    }

    public function test_scenario_produces_the_expected_action_per_hour(): void
    {
        [$results] = $this->buildFourHourSimulation();

        $this->assertSame(DecisionAction::Store, $results[0]->decision->action);
        $this->assertSame(DecisionAction::Sell, $results[1]->decision->action);
        $this->assertSame(DecisionAction::UseBattery, $results[2]->decision->action);
        $this->assertSame(DecisionAction::DrawFromGrid, $results[3]->decision->action);
    }

    public function test_baseline_cost_matches_hand_calculation(): void
    {
        [$results, $priceList] = $this->buildFourHourSimulation();
        $daily = new DailySimulation($results);

        $baseline = (new BaselineCostCalculator())->calculate($daily, $priceList);

        // h0: surplus 20 @1.5 -> -30 (sold)
        // h1: surplus 30 @3.0 -> -90 (sold)
        // h2: deficit 25 @3.0 -> +75 (bought)
        // h3: deficit 40 @2.0 -> +80 (bought)
        $this->assertEqualsWithDelta(35.0, $baseline, 0.0001);
    }

    public function test_actual_cost_and_savings_match_hand_calculation(): void
    {
        [$results, $priceList] = $this->buildFourHourSimulation();
        $daily = new DailySimulation($results);

        $baseline = (new BaselineCostCalculator())->calculate($daily, $priceList);
        $actual = $daily->actualNetCostTl();
        $savings = $baseline - $actual;

        $this->assertEqualsWithDelta(-10.0, $actual, 0.0001);
        $this->assertEqualsWithDelta(35.0, $baseline, 0.0001);
        $this->assertEqualsWithDelta(45.0, $savings, 0.0001);
    }
}
