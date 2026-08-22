<?php

namespace Tests\Unit\Simulation;

use App\Domain\Decision\DecisionAction;
use App\Domain\Decision\DecisionContext;
use App\Domain\Simulation\DailySimulation;
use App\Services\Battery\DegradationCostCalculator;
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
 * Reproduces the 5-hour scenario manually verified via tinker in Faz 6
 * (originally 4 hours; h4 was added for the curtailment fix — see below):
 * baseline (no-battery) net cost 15 TL vs. actual net cost -27.7778 TL,
 * i.e. ~42.7778 TL of savings attributable to the battery.
 */
class BaselineCostCalculatorTest extends TestCase
{
    private function engine(): DecisionEngine
    {
        return new DecisionEngine([
            new SocLimitGuardRule(),
            new StoreSurplusRule(new DegradationCostCalculator()),
            new SellSurplusRule(),
            new UseBatteryRule(),
            new DrawFromGridRule(),
        ]);
    }

    /**
     * h0: surplus 20 @1.5 (low)                              -> Store
     * h1: surplus 30 @3.0, battery already at max SOC        -> guard -> Sell
     * h2: deficit 25 @3.0 (high), battery has headroom       -> UseBattery
     * h3: deficit 40, battery already at min SOC             -> guard -> DrawFromGrid
     * h4: surplus 20 @1.0 (low), only 2 kWh of headroom left -> Store + curtailed sale
     */
    private function buildScenario(): array
    {
        $engine = $this->engine();

        $priceList = array_fill(0, 24, 2.0);
        $priceList[0] = 1.5;
        $priceList[1] = 3.0;
        $priceList[2] = 3.0;
        $priceList[3] = 2.0;
        $priceList[4] = 1.0;

        $production = [0 => 45.0, 1 => 55.0, 2 => 15.0, 3 => 15.0, 4 => 50.0];
        $consumption = [0 => 25.0, 1 => 25.0, 2 => 40.0, 3 => 55.0, 4 => 30.0];

        $batteries = [
            0 => BatteryFactory::make(['soc_percent' => 50.0]),
            1 => BatteryFactory::make(['soc_percent' => 90.0]),
            2 => BatteryFactory::make(['soc_percent' => 50.0]),
            3 => BatteryFactory::make(['soc_percent' => 10.0]),
            // efficiency 0.81 -> sqrt = 0.9 exactly, for clean arithmetic.
            4 => BatteryFactory::make(['soc_percent' => 88.0, 'efficiency_rate' => 0.81]),
        ];

        $results = [];
        foreach ([0, 1, 2, 3, 4] as $hour) {
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
        [$results] = $this->buildScenario();

        $this->assertSame(DecisionAction::Store, $results[0]->decision->action);
        $this->assertSame(DecisionAction::Sell, $results[1]->decision->action);
        $this->assertSame(DecisionAction::UseBattery, $results[2]->decision->action);
        $this->assertSame(DecisionAction::DrawFromGrid, $results[3]->decision->action);
        $this->assertSame(DecisionAction::Store, $results[4]->decision->action);
    }

    public function test_curtailed_surplus_from_a_headroom_limited_store_counts_as_a_sale(): void
    {
        [$results] = $this->buildScenario();
        $daily = new DailySimulation($results);

        // h4: headroom = 2 kWh, wanted = 20 * 0.9 = 18 kWh -> 2 kWh stored,
        // remaining 20 - 2/0.9 = 17.7778 kWh curtailed onto the market.
        $this->assertEqualsWithDelta(17.7778, $results[4]->decision->curtailedSoldKwh, 0.001);

        // h1's 30 kWh Sell + h4's curtailed 17.7778 kWh.
        $this->assertEqualsWithDelta(47.7778, $daily->totalSoldKwh(), 0.001);

        // h1 revenue (30 * 3.0 = 90) + h4 curtailed revenue (17.7778 * 1.0).
        $this->assertEqualsWithDelta(107.7778, $daily->totalRevenueTl(), 0.001);
    }

    public function test_baseline_cost_matches_hand_calculation(): void
    {
        [$results, $priceList] = $this->buildScenario();
        $daily = new DailySimulation($results);

        $baseline = (new BaselineCostCalculator())->calculate($daily, $priceList);

        // h0: surplus 20 @1.5 -> -30 (sold)
        // h1: surplus 30 @3.0 -> -90 (sold)
        // h2: deficit 25 @3.0 -> +75 (bought)
        // h3: deficit 40 @2.0 -> +80 (bought)
        // h4: surplus 20 @1.0 -> -20 (sold) — baseline has no battery, so it
        //     doesn't care that our actual system could only store part of it.
        $this->assertEqualsWithDelta(15.0, $baseline, 0.0001);
    }

    public function test_actual_cost_and_savings_match_hand_calculation(): void
    {
        [$results, $priceList] = $this->buildScenario();
        $daily = new DailySimulation($results);

        $baseline = (new BaselineCostCalculator())->calculate($daily, $priceList);
        $actual = $daily->actualNetCostTl();
        $savings = $baseline - $actual;

        $this->assertEqualsWithDelta(-27.7778, $actual, 0.001);
        $this->assertEqualsWithDelta(15.0, $baseline, 0.0001);
        $this->assertEqualsWithDelta(42.7778, $savings, 0.001);
    }
}
