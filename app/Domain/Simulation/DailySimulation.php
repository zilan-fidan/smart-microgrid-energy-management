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
