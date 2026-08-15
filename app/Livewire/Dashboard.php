<?php

namespace App\Livewire;

use App\Services\AssetService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Dashboard extends Component
{
    #[Layout('layouts.app')]
    public function render(AssetService $assets)
    {
        $battery = $assets->getBattery();
        $lastSimulation = session('last_simulation');

        $hourly = $lastSimulation['hourly'] ?? null;

        return view('livewire.dashboard', [
            'batterySocPercent' => $battery?->getSocPercent(),
            'solarCount' => count($assets->listSolarPlants()),
            'windCount' => count($assets->listWindPlants()),
            'consumptionCount' => count($assets->listConsumptionPoints()),
            'lastSimulationAt' => $lastSimulation ? Carbon::parse($lastSimulation['ranAt']) : null,
            'lastSimulationSavingsTl' => $lastSimulation['savingsTl'] ?? null,
            'lastSimulationHourly' => $hourly !== [] ? $hourly : null,
            'lastSimulationActionCounts' => array_merge(
                ['store' => 0, 'sell' => 0, 'use_battery' => 0, 'draw_from_grid' => 0],
                $lastSimulation['actionCounts'] ?? [],
            ),
        ]);
    }
}
