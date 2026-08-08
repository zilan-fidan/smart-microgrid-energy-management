<?php

namespace App\Livewire\Simulation;

use App\Domain\Contracts\SimulationRunnerInterface;
use App\Domain\Simulation\DashboardMetrics;
use App\Domain\Simulation\SimulationResult;
use App\Services\Simulation\BaselineCostCalculator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

class SimulationPanel extends Component
{
    public array $rows = [];

    public array $metrics = [];

    public bool $hasRun = false;

    public ?string $error = null;

    protected SimulationRunnerInterface $runner;

    protected BaselineCostCalculator $baselineCostCalculator;

    public function boot(SimulationRunnerInterface $runner, BaselineCostCalculator $baselineCostCalculator): void
    {
        $this->runner = $runner;
        $this->baselineCostCalculator = $baselineCostCalculator;
    }

    public function runSimulation(): void
    {
        $this->error = null;

        try {
            $daily = $this->runner->runFullDay();
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->hasRun = false;

            return;
        }

        $this->rows = array_map(fn (SimulationResult $r) => [
            'hour' => $r->hour,
            'action' => $r->decision->action->value,
            'amountKwh' => round($r->decision->amountKwh, 2),
            'socBefore' => round($r->socPercentBefore, 1),
            'socAfter' => round($r->decision->resultingSocPercent, 1),
            'production' => round($r->productionKwh, 2),
            'consumption' => round($r->consumptionKwh, 2),
            'price' => round($r->priceKwh, 2),
            'reasons' => $r->decision->reasons,
        ], $daily->results);

        $metrics = DashboardMetrics::fromDailySimulation($daily, $this->baselineCostCalculator);

        $this->metrics = [
            'totalProductionKwh' => $metrics->totalProductionKwh,
            'totalConsumptionKwh' => $metrics->totalConsumptionKwh,
            'totalStoredKwh' => $metrics->totalStoredKwh,
            'totalSoldKwh' => $metrics->totalSoldKwh,
            'totalGridDrawKwh' => $metrics->totalGridDrawKwh,
            'totalLossKwh' => $metrics->totalLossKwh,
            'projectedSohPercent' => $metrics->projectedSohPercent,
            'actualNetCostTl' => $metrics->actualNetCostTl,
            'baselineNetCostTl' => $metrics->baselineNetCostTl,
            'savingsTl' => $metrics->savingsTl,
            'finalSocPercent' => $daily->results[array_key_last($daily->results)]->decision->resultingSocPercent,
        ];

        $this->hasRun = true;

        $this->dispatch(
            'simulation-completed',
            hourly: $metrics->hourlyBreakdown,
        );
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.simulation.simulation-panel');
    }
}
