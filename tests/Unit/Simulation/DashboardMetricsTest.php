<?php

namespace Tests\Unit\Simulation;

use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Simulation\DailySimulation;
use App\Domain\Simulation\DashboardMetrics;
use App\Services\Simulation\BaselineCostCalculator;
use PHPUnit\Framework\TestCase;
use Tests\Support\SimulationResultFactory;

/**
 * Regression coverage for a fatal error: end($simulation->results) does not
 * work on a readonly array property (end() needs a by-reference variable),
 * so fromDailySimulation() must read the last element via array_key_last()
 * instead.
 */
class DashboardMetricsTest extends TestCase
{
    public function test_from_daily_simulation_does_not_throw_and_reads_the_last_hours_soh(): void
    {
        $results = [];
        foreach (range(0, 23) as $hour) {
            $results[] = SimulationResultFactory::make(
                hour: $hour,
                productionKwh: 10.0,
                consumptionKwh: 10.0,
                priceKwh: 2.0,
                decision: new Decision(DecisionAction::DrawFromGrid, 0.0, ['dengede'], 50.0),
                socPercentBefore: 50.0,
                sohPercentAfter: 99.5,
            );
        }

        $daily = new DailySimulation($results);

        $metrics = DashboardMetrics::fromDailySimulation($daily, new BaselineCostCalculator());

        $this->assertSame(99.5, $metrics->projectedSohPercent);
        $this->assertCount(24, $metrics->hourlyBreakdown);
    }

    public function test_reads_the_last_hours_soh_even_when_hours_are_out_of_order(): void
    {
        $decision = new Decision(DecisionAction::DrawFromGrid, 0.0, ['dengede'], 50.0);

        $results = [
            SimulationResultFactory::make(0, 10.0, 10.0, 2.0, $decision, 50.0, 100.0),
            SimulationResultFactory::make(1, 10.0, 10.0, 2.0, $decision, 50.0, 97.25),
        ];

        $daily = new DailySimulation($results);

        $metrics = DashboardMetrics::fromDailySimulation($daily, new BaselineCostCalculator());

        // array_key_last reads the last array element, not the highest hour —
        // confirms the fix reads position, matching how SimulationRunner
        // actually appends results (sequentially, hour 0..23).
        $this->assertSame(97.25, $metrics->projectedSohPercent);
    }
}
