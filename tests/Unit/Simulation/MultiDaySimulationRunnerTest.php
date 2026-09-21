<?php

namespace Tests\Unit\Simulation;

use App\Services\Aggregation\HourlyAggregator;
use App\Services\AssetService;
use App\Services\Battery\DegradationCostCalculator;
use App\Services\Battery\SimpleCycleDegradationRule;
use App\Services\Decision\DecisionEngine;
use App\Services\Decision\Rules\DrawFromGridRule;
use App\Services\Decision\Rules\SellSurplusRule;
use App\Services\Decision\Rules\SocLimitGuardRule;
use App\Services\Decision\Rules\StoreSurplusRule;
use App\Services\Decision\Rules\UseBatteryRule;
use App\Services\Simulation\MultiDaySimulationRunner;
use App\Services\Simulation\SimulationRunner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FixedMarketPriceProvider;
use Tests\Support\InMemoryAssetRepository;
use Tests\Support\ThrowingProfileGeneratorResolver;

/**
 * MultiDaySimulationRunner just repeats the real single-day SimulationRunner
 * N times, threading DailySimulation::$endingBattery forward as the next day's
 * starting snapshot. These tests exercise that continuity against the real
 * pipeline (no mocks below the runner) and re-assert the side-effect-free
 * guarantee across the day boundary.
 */
class MultiDaySimulationRunnerTest extends TestCase
{
    /**
     * Flat-price fixture (mirrors SimulationRunnerTest): h0 drains the battery
     * to min SOC, storing midday surplus never breaks even, so from day 1
     * onward the battery sits pinned at min — SOC carries as a constant 10%,
     * SOH stops falling after day 1's single cycle.
     */
    private function flatSeedData(): array
    {
        $production = [];
        for ($h = 0; $h < 24; $h++) {
            $production[$h] = ($h >= 8 && $h <= 16) ? 90.0 : 5.0;
        }

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
                'replacement_cost_tl' => 5000.0,
            ]],
            'consumption_points' => [[
                'id' => 'c1',
                'name' => 'Test Load',
                'average_demand_kwh' => 40.0,
                'hourly_demand_kwh' => array_fill(0, 24, 40.0),
            ]],
        ];
    }

    private function flatPriceList(): array
    {
        $prices = array_fill(0, 24, 2.0);
        $prices[3] = 1.4;
        $prices[19] = 3.1;

        return $prices;
    }

    /**
     * Cycling fixture: deficit every hour except a midday surplus block, a
     * fat evening price spike so storing that surplus breaks even, and the
     * battery starting near full. Result: it charges midday and discharges
     * every night, cycling the cell on every single day — so SOH must fall
     * day over day, not just once.
     */
    private function cyclingSeedData(): array
    {
        $production = [];
        for ($h = 0; $h < 24; $h++) {
            $production[$h] = ($h >= 10 && $h <= 14) ? 120.0 : 2.0;
        }

        return [
            'solar_plants' => [[
                'id' => 's1',
                'name' => 'Cycling Solar',
                'capacity_kw' => 120.0,
                'hourly_output_kwh' => $production,
            ]],
            'wind_plants' => [],
            'batteries' => [[
                'id' => 'b1',
                'name' => 'Cycling Battery',
                'capacity_kwh' => 50.0,
                'soc_percent' => 90.0,
                'min_soc' => 10.0,
                'max_soc' => 90.0,
                'efficiency_rate' => 0.9,
                'soh_percent' => 100.0,
                'replacement_cost_tl' => 5000.0,
            ]],
            'consumption_points' => [[
                'id' => 'c1',
                'name' => 'Cycling Load',
                'average_demand_kwh' => 40.0,
                'hourly_demand_kwh' => array_fill(0, 24, 40.0),
            ]],
        ];
    }

    private function cyclingPriceList(): array
    {
        $prices = array_fill(0, 24, 1.5);
        $prices[18] = 3.1;
        $prices[19] = 3.1;
        $prices[20] = 3.1;

        return $prices;
    }

    private function buildDayRunner(InMemoryAssetRepository $repository, array $priceList): SimulationRunner
    {
        $assetService = new AssetService($repository, new ThrowingProfileGeneratorResolver);
        $aggregator = new HourlyAggregator($assetService, new FixedMarketPriceProvider($priceList));
        $engine = new DecisionEngine([
            new SocLimitGuardRule,
            new StoreSurplusRule(new DegradationCostCalculator),
            new SellSurplusRule,
            new UseBatteryRule,
            new DrawFromGridRule,
        ]);

        return new SimulationRunner($aggregator, $engine, new SimpleCycleDegradationRule);
    }

    public function test_running_n_days_returns_n_daily_simulations_each_with_24_hours(): void
    {
        $repository = new InMemoryAssetRepository($this->flatSeedData());
        $runner = new MultiDaySimulationRunner($this->buildDayRunner($repository, $this->flatPriceList()));

        $days = $runner->runMultipleDays(5);

        $this->assertCount(5, $days);
        foreach ($days as $daily) {
            $this->assertCount(24, $daily->results);
            $this->assertNotNull($daily->endingBattery);
        }
    }

    public function test_rejects_a_day_count_below_one(): void
    {
        $repository = new InMemoryAssetRepository($this->flatSeedData());
        $runner = new MultiDaySimulationRunner($this->buildDayRunner($repository, $this->flatPriceList()));

        $this->expectException(InvalidArgumentException::class);

        $runner->runMultipleDays(0);
    }

    public function test_first_day_is_identical_to_a_standalone_single_day_run(): void
    {
        // OCP check: wrapping the runner must not change the 24h run itself.
        $repoA = new InMemoryAssetRepository($this->flatSeedData());
        $standalone = $this->buildDayRunner($repoA, $this->flatPriceList())->runFullDay();

        $repoB = new InMemoryAssetRepository($this->flatSeedData());
        $multi = new MultiDaySimulationRunner($this->buildDayRunner($repoB, $this->flatPriceList()));
        $firstDay = $multi->runMultipleDays(3)[0];

        foreach ($standalone->results as $hour => $expected) {
            $this->assertSame($expected->decision->action, $firstDay->results[$hour]->decision->action);
            $this->assertEqualsWithDelta(
                $expected->decision->resultingSocPercent,
                $firstDay->results[$hour]->decision->resultingSocPercent,
                0.0001,
                "SOC diverged at hour {$hour}",
            );
            $this->assertEqualsWithDelta(
                $expected->sohPercentAfter,
                $firstDay->results[$hour]->sohPercentAfter,
                0.0001,
                "SOH diverged at hour {$hour}",
            );
        }
    }

    public function test_soc_carries_across_every_day_boundary_without_a_gap(): void
    {
        $repository = new InMemoryAssetRepository($this->flatSeedData());
        $runner = new MultiDaySimulationRunner($this->buildDayRunner($repository, $this->flatPriceList()));

        $days = $runner->runMultipleDays(4);

        for ($d = 1; $d < count($days); $d++) {
            $previousDayEndSoc = $days[$d - 1]->results[23]->decision->resultingSocPercent;
            $thisDayStartSoc = $days[$d]->results[0]->socPercentBefore;

            $this->assertSame(
                $previousDayEndSoc,
                $thisDayStartSoc,
                "SOC gap between day {$d} start and day ".($d - 1).' end',
            );
        }
    }

    public function test_ending_battery_snapshot_matches_the_last_hour_of_its_own_day(): void
    {
        $repository = new InMemoryAssetRepository($this->flatSeedData());
        $runner = new MultiDaySimulationRunner($this->buildDayRunner($repository, $this->flatPriceList()));

        foreach ($runner->runMultipleDays(3) as $index => $daily) {
            $this->assertSame(
                $daily->results[23]->sohPercentAfter,
                $daily->endingBattery->getSohPercent(),
                'endingBattery SOH out of sync with hour 23 on day '.($index + 1),
            );
            $this->assertSame(
                $daily->results[23]->decision->resultingSocPercent,
                $daily->endingBattery->getSocPercent(),
                'endingBattery SOC out of sync with hour 23 on day '.($index + 1),
            );
        }
    }

    public function test_soh_never_recovers_between_days(): void
    {
        $repository = new InMemoryAssetRepository($this->flatSeedData());
        $runner = new MultiDaySimulationRunner($this->buildDayRunner($repository, $this->flatPriceList()));

        $days = $runner->runMultipleDays(5);

        for ($d = 1; $d < count($days); $d++) {
            $this->assertLessThanOrEqual(
                $days[$d - 1]->endingBattery->getSohPercent(),
                $days[$d]->endingBattery->getSohPercent(),
                'SOH went back up between day '.($d - 1)." and day {$d}",
            );
        }

        // At least the first day's h0 drain wore the cell.
        $this->assertLessThan(100.0, $days[array_key_last($days)]->endingBattery->getSohPercent());
    }

    public function test_soh_keeps_falling_day_over_day_when_the_battery_cycles_daily(): void
    {
        $repository = new InMemoryAssetRepository($this->cyclingSeedData());
        $runner = new MultiDaySimulationRunner($this->buildDayRunner($repository, $this->cyclingPriceList()));

        $days = $runner->runMultipleDays(4);

        for ($d = 1; $d < count($days); $d++) {
            $this->assertLessThan(
                $days[$d - 1]->endingBattery->getSohPercent(),
                $days[$d]->endingBattery->getSohPercent(),
                'SOH did not decrease from day '.($d - 1)." to day {$d} despite daily cycling",
            );
        }
    }

    public function test_multi_day_run_never_writes_to_the_repository(): void
    {
        $repository = new InMemoryAssetRepository($this->flatSeedData());
        $before = $repository->snapshot();

        $runner = new MultiDaySimulationRunner($this->buildDayRunner($repository, $this->flatPriceList()));
        $runner->runMultipleDays(10);

        $this->assertSame(0, $repository->saveCallCount, 'runMultipleDays() must never call save()');
        $this->assertSame(0, $repository->deleteCallCount, 'runMultipleDays() must never call delete()');
        $this->assertSame($before, $repository->snapshot(), 'persisted data must be byte-for-byte unchanged');
    }
}
