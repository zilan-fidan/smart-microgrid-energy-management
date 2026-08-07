<?php

namespace App\Services\Simulation;

use App\Domain\Assets\Battery;
use App\Domain\Contracts\BatteryDegradationRuleInterface;
use App\Domain\Contracts\DecisionEngineInterface;
use App\Domain\Contracts\HourlyAggregatorInterface;
use App\Domain\Contracts\SimulationRunnerInterface;
use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Simulation\DailySimulation;
use App\Domain\Simulation\SimulationResult;

class SimulationRunner implements SimulationRunnerInterface
{
    public function __construct(
        private readonly HourlyAggregatorInterface $aggregator,
        private readonly DecisionEngineInterface $decisionEngine,
        private readonly BatteryDegradationRuleInterface $degradationRule,
    ) {
    }

    public function runFullDay(): DailySimulation
    {
        $results = [];

        // Null for hour 0 lets the aggregator load the real persisted battery
        // as the simulation's starting point. From then on we thread an
        // immutable snapshot forward ourselves — the real record is never
        // touched, so a "what-if" run can't corrupt stored SOC (or SOH).
        $batterySnapshot = null;

        for ($hour = 0; $hour < 24; $hour++) {
            $context = $this->aggregator->aggregate($hour, $batterySnapshot);
            $decision = $this->decisionEngine->decide($context);

            $socUpdated = Battery::fromArray(array_merge(
                $context->battery->toArray(),
                ['soc_percent' => $decision->resultingSocPercent],
            ));

            $batterySnapshot = $this->degradationRule->applyDegradation(
                $socUpdated,
                $this->cycledKwhFor($decision),
            );

            $results[] = new SimulationResult(
                hour: $hour,
                decision: $decision,
                productionKwh: $context->totalProductionKwh,
                consumptionKwh: $context->totalConsumptionKwh,
                priceKwh: $context->priceKwh,
                socPercentBefore: $context->battery->getSocPercent(),
                sohPercentAfter: $batterySnapshot->getSohPercent(),
            );
        }

        return new DailySimulation($results);
    }

    /**
     * Gross energy moved through the battery this hour (charge or
     * discharge side, including its own efficiency loss) — Sell/DrawFromGrid
     * never touch the battery, so they contribute nothing to cycle wear.
     */
    private function cycledKwhFor(Decision $decision): float
    {
        return match ($decision->action) {
            DecisionAction::Store, DecisionAction::UseBattery => $decision->amountKwh + $decision->lossKwh,
            default => 0.0,
        };
    }
}
