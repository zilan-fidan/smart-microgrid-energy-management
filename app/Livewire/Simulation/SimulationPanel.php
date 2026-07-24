<?php

namespace App\Livewire\Simulation;

use App\Domain\Contracts\SimulationRunnerInterface;
use App\Domain\Simulation\SimulationResult;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

class SimulationPanel extends Component
{
    public array $rows = [];

    public bool $hasRun = false;

    public ?string $error = null;

    protected SimulationRunnerInterface $runner;

    public function boot(SimulationRunnerInterface $runner): void
    {
        $this->runner = $runner;
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

        $this->hasRun = true;
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.simulation.simulation-panel');
    }
}
