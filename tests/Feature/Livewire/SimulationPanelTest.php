<?php

namespace Tests\Feature\Livewire;

use App\Domain\Contracts\AssetRepositoryInterface;
use App\Livewire\Simulation\SimulationPanel;
use Livewire\Livewire;
use Tests\Support\InMemoryAssetRepository;
use Tests\TestCase;

/**
 * Regression coverage for a fatal error: SimulationPanel::runSimulation()
 * used to call end() on a readonly array property (DailySimulation::$results
 * via DashboardMetrics, and $daily->results directly), which is a hard
 * error in PHP — end() requires a by-reference variable. array_key_last()
 * fixed both spots; this test exercises the full component call so a
 * regression here fails loudly instead of only being caught by unit tests
 * that never touch the Livewire component.
 */
class SimulationPanelTest extends TestCase
{
    public function test_running_the_simulation_does_not_throw_and_populates_metrics(): void
    {
        $this->app->instance(AssetRepositoryInterface::class, new InMemoryAssetRepository([
            'solar_plants' => [[
                'id' => 's1',
                'name' => 'Test Solar',
                'capacity_kw' => 80.0,
                'hourly_output_kwh' => array_fill(0, 24, 10.0),
            ]],
            'wind_plants' => [],
            'batteries' => [[
                'id' => 'b1',
                'name' => 'Test Battery',
                'capacity_kwh' => 100.0,
                'soc_percent' => 50.0,
                'min_soc' => 10.0,
                'max_soc' => 90.0,
                'efficiency_rate' => 0.9,
                'soh_percent' => 100.0,
            ]],
            'consumption_points' => [[
                'id' => 'c1',
                'name' => 'Test Load',
                'average_demand_kwh' => 8.0,
                'hourly_demand_kwh' => array_fill(0, 24, 8.0),
            ]],
        ]));

        Livewire::test(SimulationPanel::class)
            ->call('runSimulation')
            ->assertSet('error', null)
            ->assertSet('hasRun', true)
            ->assertCount('rows', 24);
    }

    public function test_multi_day_simulation_populates_a_cumulative_savings_summary(): void
    {
        $this->app->instance(AssetRepositoryInterface::class, new InMemoryAssetRepository([
            'solar_plants' => [[
                'id' => 's1',
                'name' => 'Test Solar',
                'capacity_kw' => 80.0,
                'hourly_output_kwh' => array_fill(0, 24, 10.0),
            ]],
            'wind_plants' => [],
            'batteries' => [[
                'id' => 'b1',
                'name' => 'Test Battery',
                'capacity_kwh' => 100.0,
                'soc_percent' => 50.0,
                'min_soc' => 10.0,
                'max_soc' => 90.0,
                'efficiency_rate' => 0.9,
                'soh_percent' => 100.0,
                'replacement_cost_tl' => 10000.0,
            ]],
            'consumption_points' => [[
                'id' => 'c1',
                'name' => 'Test Load',
                'average_demand_kwh' => 8.0,
                'hourly_demand_kwh' => array_fill(0, 24, 8.0),
            ]],
        ]));

        $component = Livewire::test(SimulationPanel::class)
            ->set('multiDays', 5)
            ->call('runMultiDaySimulation')
            ->assertSet('multiDayError', null)
            ->assertSet('hasRunMultiDay', true);

        $metrics = $component->get('multiDayMetrics');

        $this->assertSame(5, $metrics['days']);
        $this->assertCount(5, $metrics['dailySavingsTl']);
        $this->assertCount(5, $metrics['cumulativeSavingsTl']);
        $this->assertSame(10000.0, $metrics['totalInvestmentTl']);
    }

    public function test_multi_day_input_is_clamped_to_the_allowed_range(): void
    {
        $this->app->instance(AssetRepositoryInterface::class, new InMemoryAssetRepository([
            'solar_plants' => [],
            'wind_plants' => [],
            'batteries' => [[
                'id' => 'b1',
                'name' => 'Test Battery',
                'capacity_kwh' => 100.0,
                'soc_percent' => 50.0,
                'min_soc' => 10.0,
                'max_soc' => 90.0,
                'efficiency_rate' => 0.9,
                'soh_percent' => 100.0,
            ]],
            'consumption_points' => [[
                'id' => 'c1',
                'name' => 'Test Load',
                'average_demand_kwh' => 8.0,
                'hourly_demand_kwh' => array_fill(0, 24, 8.0),
            ]],
        ]));

        Livewire::test(SimulationPanel::class)
            ->set('multiDays', 5000)
            ->call('runMultiDaySimulation')
            ->assertSet('multiDays', SimulationPanel::MAX_MULTI_DAYS)
            ->assertSet('hasRunMultiDay', true);
    }
}
