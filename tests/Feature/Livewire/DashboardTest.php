<?php

namespace Tests\Feature\Livewire;

use App\Domain\Contracts\AssetRepositoryInterface;
use App\Livewire\Dashboard;
use App\Livewire\Simulation\SimulationPanel;
use Livewire\Livewire;
use Tests\Support\InMemoryAssetRepository;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_dashboard_hides_last_simulation_summary_when_no_simulation_has_run(): void
    {
        Livewire::test(Dashboard::class)
            ->assertViewHas('lastSimulationHourly', null)
            ->assertDontSee('Son Simülasyon Özeti');
    }

    public function test_dashboard_shows_last_simulation_summary_after_simulation_runs(): void
    {
        Livewire::test(SimulationPanel::class)->call('runSimulation');

        Livewire::test(Dashboard::class)
            ->assertSee('Son Simülasyon Özeti')
            ->assertViewHas('lastSimulationHourly', fn (?array $hourly) => count($hourly) === 24)
            ->assertViewHas('lastSimulationActionCounts', fn (array $counts) => array_sum($counts) === 24);
    }
}
