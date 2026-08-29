<?php

namespace Tests\Unit\Simulation;

use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Simulation\DailySimulation;
use App\Domain\Simulation\MultiDaySummary;
use App\Domain\Simulation\SimulationResult;
use App\Services\Simulation\BaselineCostCalculator;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;

/**
 * MultiDaySummary turns a list of DailySimulation into a payback view:
 * per-day savings, their running total, and the day the running total first
 * clears the battery's replacement cost (or a linear projection past the
 * simulated horizon).
 *
 * Each day below is a hand-built 3-hour DailySimulation rigged so its
 * savingsTl (baseline minus actual, via DashboardMetrics) equals a known
 * number: hour 0 has a pure deficit of `S` kWh at 1.00 TL/kWh with no
 * battery action, the other two hours are perfectly balanced. Actual net
 * cost is 0 (every decision is a zero-amount DrawFromGrid), so
 * savingsTl == baseline == S.
 */
class MultiDaySummaryTest extends TestCase
{
    private const IDLE_DECISION_SOC = 50.0;

    private function dayWithSavings(float $savingsTl, float $replacementCostTl): DailySimulation
    {
        $idle = new Decision(DecisionAction::DrawFromGrid, 0.0, ['test'], self::IDLE_DECISION_SOC);

        $results = [
            // Deficit of exactly $savingsTl kWh at 1.00 TL/kWh -> baseline cost $savingsTl.
            new SimulationResult(0, $idle, 0.0, $savingsTl, 1.0, self::IDLE_DECISION_SOC, 100.0),
            new SimulationResult(1, $idle, 10.0, 10.0, 2.0, self::IDLE_DECISION_SOC, 100.0),
            new SimulationResult(2, $idle, 10.0, 10.0, 2.0, self::IDLE_DECISION_SOC, 100.0),
        ];

        $battery = BatteryFactory::make(['replacement_cost_tl' => $replacementCostTl]);

        return new DailySimulation($results, $battery);
    }

    public function test_daily_and_cumulative_savings_are_computed_per_day(): void
    {
        $days = [
            $this->dayWithSavings(10.0, 25.0),
            $this->dayWithSavings(20.0, 25.0),
            $this->dayWithSavings(5.0, 25.0),
        ];

        $summary = MultiDaySummary::fromDailySimulations($days, new BaselineCostCalculator);

        $this->assertSame([10.0, 20.0, 5.0], $summary->dailySavingsTl);
        $this->assertSame([10.0, 30.0, 35.0], $summary->cumulativeSavingsTl);
        $this->assertSame(25.0, $summary->totalInvestmentTl);
    }

    public function test_payback_day_is_the_first_day_cumulative_savings_reach_the_investment(): void
    {
        $days = [
            $this->dayWithSavings(10.0, 25.0),  // cumulative 10  -> not yet
            $this->dayWithSavings(20.0, 25.0),  // cumulative 30  -> clears 25 here
            $this->dayWithSavings(5.0, 25.0),
        ];

        $summary = MultiDaySummary::fromDailySimulations($days, new BaselineCostCalculator);

        $this->assertSame(2, $summary->estimatedPaybackDays);
    }

    public function test_payback_is_linearly_projected_when_not_reached_within_the_horizon(): void
    {
        $days = [
            $this->dayWithSavings(10.0, 100.0),
            $this->dayWithSavings(20.0, 100.0),
            $this->dayWithSavings(5.0, 100.0),
        ];

        // cumulative maxes at 35 < 100. Mean daily saving = 35 / 3 = 11.667.
        // ceil(100 / 11.667) = ceil(8.571) = 9.
        $summary = MultiDaySummary::fromDailySimulations($days, new BaselineCostCalculator);

        $this->assertSame(9, $summary->estimatedPaybackDays);
    }

    public function test_payback_is_null_when_the_average_daily_saving_is_not_positive(): void
    {
        $days = [
            $this->dayWithSavings(0.0, 50.0),
            $this->dayWithSavings(0.0, 50.0),
        ];

        $summary = MultiDaySummary::fromDailySimulations($days, new BaselineCostCalculator);

        $this->assertNull($summary->estimatedPaybackDays);
    }

    public function test_zero_investment_pays_back_on_day_one(): void
    {
        $days = [
            $this->dayWithSavings(3.0, 0.0),
            $this->dayWithSavings(3.0, 0.0),
        ];

        $summary = MultiDaySummary::fromDailySimulations($days, new BaselineCostCalculator);

        $this->assertSame(1, $summary->estimatedPaybackDays);
        $this->assertSame(0.0, $summary->totalInvestmentTl);
    }
}
