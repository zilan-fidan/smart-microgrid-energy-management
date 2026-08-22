<?php

namespace Tests\Unit\Simulation;

use App\Domain\Decision\DecisionAction;
use App\Services\Aggregation\HourlyAggregator;
use App\Services\AssetService;
use App\Services\Battery\SimpleCycleDegradationRule;
use App\Services\Decision\DecisionEngine;
use App\Services\Decision\Rules\DrawFromGridRule;
use App\Services\Decision\Rules\SellSurplusRule;
use App\Services\Decision\Rules\SocLimitGuardRule;
use App\Services\Decision\Rules\StoreSurplusRule;
use App\Services\Decision\Rules\UseBatteryRule;
use App\Services\Simulation\SimulationRunner;
use PHPUnit\Framework\TestCase;
use Tests\Support\FixedMarketPriceProvider;
use Tests\Support\InMemoryAssetRepository;
use Tests\Support\ThrowingProfileGeneratorResolver;

/**
 * Exercises the real SimulationRunner -> HourlyAggregator -> AssetService ->
 * DecisionEngine -> SimpleCycleDegradationRule pipeline end to end, using
 * the exact solar/consumption/battery/price fixture manually verified via
 * tinker in Faz 6 (see the SOH trace in that conversation) — so the
 * per-hour actions/SOC/SOH assertions below are regression checks against
 * previously validated output, not newly guessed numbers.
 *
 * Mentor recommendation #1 (round-trip breakeven for StoreSurplusRule)
 * changed the trace: with this fixture's flat 2.0 TL/kWh pricing (a single
 * evening spike at h19 that no longer overlaps any surplus hour), storing
 * at h8-h16 is never profitable once round-trip efficiency is priced in —
 * the expected future sell price, discounted by efficiency, never clears
 * the current price. The battery now only cycles once (h0's deficit drain
 * to min SOC) instead of also charging at h8 and discharging at h17-h18;
 * every surplus hour sells directly instead. Re-verified against the real
 * pipeline output (not hand-derived) after the rule change.
 */
class SimulationRunnerTest extends TestCase
{
    private function seedData(): array
    {
        $production = [];
        for ($h = 0; $h < 24; $h++) {
            $production[$h] = ($h >= 8 && $h <= 16) ? 90.0 : 5.0;
        }

        $consumption = array_fill(0, 24, 40.0);

        return [
            'solar_plants' => [[
                'id' => 's1',
                'name' => 'Test Solar',
                'capacity_kw' => 90.0,
                'hourly_output_kwh' => $production,
            ]],
            'wind_plants' => [],
            'batteries' => [[
                'id' => 'b1',
                'name' => 'Test Battery',
                'capacity_kwh' => 50.0,
                'soc_percent' => 50.0,
                'min_soc' => 10.0,
                'max_soc' => 90.0,
                'efficiency_rate' => 0.9,
                'soh_percent' => 100.0,
            ]],
            'consumption_points' => [[
                'id' => 'c1',
                'name' => 'Test Load',
                'average_demand_kwh' => 40.0,
                'hourly_demand_kwh' => $consumption,
            ]],
        ];
    }

    private function priceList(): array
    {
        $prices = array_fill(0, 24, 2.0);
        $prices[3] = 1.4;
        $prices[19] = 3.1;

        return $prices;
    }

    private function buildRunner(InMemoryAssetRepository $repository): SimulationRunner
    {
        $assetService = new AssetService($repository, new ThrowingProfileGeneratorResolver());
        $aggregator = new HourlyAggregator($assetService, new FixedMarketPriceProvider($this->priceList()));
        $engine = new DecisionEngine([
            new SocLimitGuardRule(),
            new StoreSurplusRule(),
            new SellSurplusRule(),
            new UseBatteryRule(),
            new DrawFromGridRule(),
        ]);

        return new SimulationRunner($aggregator, $engine, new SimpleCycleDegradationRule());
    }

    public function test_run_full_day_returns_24_hourly_results(): void
    {
        $repository = new InMemoryAssetRepository($this->seedData());
        $daily = $this->buildRunner($repository)->runFullDay();

        $this->assertCount(24, $daily->results);

        foreach ($daily->results as $index => $result) {
            $this->assertSame($index, $result->hour);
        }
    }

    public function test_soc_carries_across_hours_without_gaps(): void
    {
        $repository = new InMemoryAssetRepository($this->seedData());
        $daily = $this->buildRunner($repository)->runFullDay();

        for ($i = 1; $i < 24; $i++) {
            $previousAfter = $daily->results[$i - 1]->decision->resultingSocPercent;
            $currentBefore = $daily->results[$i]->socPercentBefore;

            $this->assertSame(
                $previousAfter,
                $currentBefore,
                "SOC gap between hour {$i} and hour ".($i - 1)
            );
        }
    }

    public function test_reproduces_the_previously_verified_daily_trace(): void
    {
        $repository = new InMemoryAssetRepository($this->seedData());
        $daily = $this->buildRunner($repository)->runFullDay();
        $results = $daily->results;

        // h0: deficit 35, battery drains from 50% down to min (10%).
        $this->assertSame(DecisionAction::UseBattery, $results[0]->decision->action);
        $this->assertEqualsWithDelta(10.0, $results[0]->decision->resultingSocPercent, 0.01);

        // h1-h7: battery pinned at min SOC -> guard redirects to grid.
        foreach (range(1, 7) as $h) {
            $this->assertSame(DecisionAction::DrawFromGrid, $results[$h]->decision->action);
            $this->assertEqualsWithDelta(10.0, $results[$h]->decision->resultingSocPercent, 0.01);
        }

        // h8-h16: solar surplus, but storing it would never break even once round-trip
        // efficiency is priced in against the expected future sell price (flat 2.0
        // pricing, no upcoming spike to arbitrage) -> sold directly, battery untouched.
        foreach (range(8, 16) as $h) {
            $this->assertSame(DecisionAction::Sell, $results[$h]->decision->action);
            $this->assertEqualsWithDelta(10.0, $results[$h]->decision->resultingSocPercent, 0.01);
        }

        // h17-h23: battery still pinned at min from h0 (it never recharged) -> grid for the rest of the day.
        foreach (range(17, 23) as $h) {
            $this->assertSame(DecisionAction::DrawFromGrid, $results[$h]->decision->action);
            $this->assertEqualsWithDelta(10.0, $results[$h]->decision->resultingSocPercent, 0.01);
        }

        // Only h0's deficit drain cycles the cell now — h8/h17/h18 no longer
        // charge or discharge it, so wear is smaller than before this change.
        $finalSoh = $results[23]->sohPercentAfter;
        $this->assertLessThan(100.0, $finalSoh);
        $this->assertEqualsWithDelta(99.9800, $finalSoh, 0.001);
    }

    public function test_running_the_simulation_never_writes_to_the_repository(): void
    {
        $repository = new InMemoryAssetRepository($this->seedData());
        $before = $repository->snapshot();

        $this->buildRunner($repository)->runFullDay();

        $this->assertSame(0, $repository->saveCallCount, 'runFullDay() must never call save()');
        $this->assertSame(0, $repository->deleteCallCount, 'runFullDay() must never call delete()');
        $this->assertSame($before, $repository->snapshot(), 'persisted data must be byte-for-byte unchanged');
    }
}
