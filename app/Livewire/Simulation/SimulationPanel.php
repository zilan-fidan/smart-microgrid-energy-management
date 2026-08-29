<?php

namespace App\Livewire\Simulation;

use App\Domain\Contracts\MultiDaySimulationRunnerInterface;
use App\Domain\Contracts\SimulationRunnerInterface;
use App\Domain\Decision\DecisionAction;
use App\Domain\Simulation\DashboardMetrics;
use App\Domain\Simulation\MultiDaySummary;
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

    /** Independent "cumulative savings / payback" feature — separate from the 24h run above. */
    public int $multiDays = 7;

    public array $multiDayMetrics = [];

    public bool $hasRunMultiDay = false;

    public ?string $multiDayError = null;

    /** Hard ceiling so a runaway input can't spin the runner for minutes. */
    public const MAX_MULTI_DAYS = 90;

    protected SimulationRunnerInterface $runner;

    protected MultiDaySimulationRunnerInterface $multiDayRunner;

    protected BaselineCostCalculator $baselineCostCalculator;

    public function boot(
        SimulationRunnerInterface $runner,
        MultiDaySimulationRunnerInterface $multiDayRunner,
        BaselineCostCalculator $baselineCostCalculator,
    ): void {
        $this->runner = $runner;
        $this->multiDayRunner = $multiDayRunner;
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
            'expectedProfitTl' => $r->decision->action === DecisionAction::Store
                ? round($r->decision->expectedProfitTl, 2)
                : null,
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

        session([
            'last_simulation' => [
                'ranAt' => now()->toIso8601String(),
                'savingsTl' => $metrics->savingsTl,
                'hourly' => array_map(fn (array $h) => [
                    'hour' => $h['hour'],
                    'soc' => $h['soc'],
                ], $metrics->hourlyBreakdown),
                'actionCounts' => array_count_values(
                    array_map(fn (SimulationResult $r) => $r->decision->action->value, $daily->results),
                ),
            ],
        ]);

        $this->dispatch(
            'simulation-completed',
            hourly: $metrics->hourlyBreakdown,
        );
    }

    public function runMultiDaySimulation(): void
    {
        $this->multiDayError = null;

        $days = max(1, min(self::MAX_MULTI_DAYS, $this->multiDays));
        $this->multiDays = $days;

        try {
            $dailySimulations = $this->multiDayRunner->runMultipleDays($days);
        } catch (RuntimeException $e) {
            $this->multiDayError = $e->getMessage();
            $this->hasRunMultiDay = false;

            return;
        }

        $summary = MultiDaySummary::fromDailySimulations($dailySimulations, $this->baselineCostCalculator);

        $paybackWithinRange = $summary->estimatedPaybackDays !== null
            && $summary->estimatedPaybackDays <= $days;

        $this->multiDayMetrics = [
            'days' => $days,
            'dailySavingsTl' => $summary->dailySavingsTl,
            'cumulativeSavingsTl' => $summary->cumulativeSavingsTl,
            'totalInvestmentTl' => $summary->totalInvestmentTl,
            'estimatedPaybackDays' => $summary->estimatedPaybackDays,
            'paybackWithinRange' => $paybackWithinRange,
            'totalSavingsTl' => $summary->cumulativeSavingsTl[array_key_last($summary->cumulativeSavingsTl)] ?? 0.0,
        ];

        $this->hasRunMultiDay = true;

        $this->dispatch(
            'multi-day-simulation-completed',
            days: $days,
            cumulativeSavingsTl: $summary->cumulativeSavingsTl,
            totalInvestmentTl: $summary->totalInvestmentTl,
        );
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.simulation.simulation-panel');
    }
}
