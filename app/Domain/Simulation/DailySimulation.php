<?php

namespace App\Domain\Simulation;

use App\Domain\Decision\DecisionAction;

class DailySimulation
{
    /**
     * @param  SimulationResult[]  $results  Exactly 24, indexed 0-23.
     */
    public function __construct(
        public readonly array $results,
    ) {
    }

    public function totalStoredKwh(): float
    {
        return $this->sumAmountFor(DecisionAction::Store);
    }

    public function totalSoldKwh(): float
    {
        return $this->sumAmountFor(DecisionAction::Sell);
    }

    public function totalUsedFromBatteryKwh(): float
    {
        return $this->sumAmountFor(DecisionAction::UseBattery);
    }

    public function totalDrawnFromGridKwh(): float
    {
        return $this->sumAmountFor(DecisionAction::DrawFromGrid);
    }

    /**
     * Round-trip efficiency losses, summed across every hour (Store and
     * UseBattery are the only actions that ever carry a non-zero loss).
     */
    public function totalLossKwh(): float
    {
        return array_sum(array_map(fn (SimulationResult $r) => $r->decision->lossKwh, $this->results));
    }

    /**
     * Revenue from market sales (TL).
     */
    public function totalRevenueTl(): float
    {
        return $this->sumValueFor(DecisionAction::Sell);
    }

    /**
     * Cost of energy drawn from the grid (TL).
     */
    public function totalGridCostTl(): float
    {
        return $this->sumValueFor(DecisionAction::DrawFromGrid);
    }

    /**
     * Net real cash flow (TL): grid purchases minus market sales. Store/
     * UseBattery move no money directly, so they're excluded — their value
     * shows up indirectly as a smaller totalGridCostTl.
     */
    public function actualNetCostTl(): float
    {
        return $this->totalGridCostTl() - $this->totalRevenueTl();
    }

    private function sumAmountFor(DecisionAction $action): float
    {
        return array_sum(array_map(
            fn (SimulationResult $r) => $r->decision->action === $action ? $r->decision->amountKwh : 0.0,
            $this->results,
        ));
    }

    private function sumValueFor(DecisionAction $action): float
    {
        return array_sum(array_map(
            fn (SimulationResult $r) => $r->decision->action === $action
                ? $r->decision->amountKwh * $r->priceKwh
                : 0.0,
            $this->results,
        ));
    }
}
