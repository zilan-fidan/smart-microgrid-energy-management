<?php

namespace App\Domain\Decision;

class Decision
{
    /**
     * @param  string[]  $reasons  Human-readable justification, for explainability
     * @param  float  $lossKwh  Round-trip efficiency loss incurred by this decision
     *                          (0 for Sell/DrawFromGrid — no battery cycling involved)
     */
    public function __construct(
        public readonly DecisionAction $action,
        public readonly float $amountKwh,
        public readonly array $reasons,
        public readonly float $resultingSocPercent,
        public readonly float $lossKwh = 0.0,
    ) {
    }
}
