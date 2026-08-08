<?php

namespace App\Domain\Simulation;

use App\Domain\Decision\Decision;

class SimulationResult
{
    public function __construct(
        public readonly int $hour,
        public readonly Decision $decision,
        public readonly float $productionKwh,
        public readonly float $consumptionKwh,
        public readonly float $priceKwh,
        public readonly float $socPercentBefore,
        public readonly float $sohPercentAfter,
    ) {
    }
}
