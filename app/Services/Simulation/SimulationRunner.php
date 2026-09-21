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

    public function runFullDay(?Battery $startingBattery = null): DailySimulation
    {
        $results = [];

        // Null for hour 0 lets the aggregator load the real persisted battery
        // as the simulation's starting point. From then on we thread an
        // immutable snapshot forward ourselves — the real record is never
        // touched, so a "what-if" run can't corrupt stored SOC (or SOH).
        //
        // A caller may instead hand us a starting snapshot (the previous day's
        // ending Battery in a multi-day run): same immutable-snapshot chain,
        // just extended across the day boundary. Still no persistence writes.
        $batterySnapshot = $startingBattery;

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

        // $batterySnapshot now holds hour 23's post-degradation state: the
        // end-of-day SOC and the cumulatively worn SOH. A multi-day runner
        // feeds this straight back in as the next day's $startingBattery.
        return new DailySimulation($results, $batterySnapshot);
    }

    /**
     * Energy that actually cycled through the battery cell this hour —
     * Sell/DrawFromGrid never touch the battery, so they contribute nothing.
     *
     * Store's lossKwh is dissipated on the charging leg (converter/chemical
     * loss) BEFORE the energy reaches the cell, so only amountKwh (what
     * actually lands in storage) cycles the cell. UseBattery's lossKwh is
     * the opposite: amountKwh + lossKwh is what's drawn OUT of the cell
     * (amountKwh is what survives to reach the load), so the full raw
     * discharge — including its own loss — is what wears the cell.
     */
    private function cycledKwhFor(Decision $decision): float
    {
        return match ($decision->action) {
            DecisionAction::Store => $decision->amountKwh,
            DecisionAction::UseBattery => $decision->amountKwh + $decision->lossKwh,
            default => 0.0,
        };
    }
}
