<?php

namespace App\Domain\Decision;

class Decision
{
    /**
     * @param  string[]  $reasons  Human-readable justification, for explainability
     */
    public function __construct(
        public readonly DecisionAction $action,
        public readonly float $amountKwh,
        public readonly array $reasons,
        public readonly float $resultingSocPercent,
    ) {
    }
}
