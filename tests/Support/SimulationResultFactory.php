<?php

namespace Tests\Support;

use App\Domain\Decision\Decision;
use App\Domain\Decision\DecisionAction;
use App\Domain\Simulation\SimulationResult;

class SimulationResultFactory
{
    public static function make(
        int $hour,
        float $productionKwh,
        float $consumptionKwh,
        float $priceKwh,
        ?Decision $decision = null,
        float $socPercentBefore = 50.0,
        float $sohPercentAfter = 100.0,
    ): SimulationResult {
        return new SimulationResult(
            hour: $hour,
            decision: $decision ?? new Decision(DecisionAction::DrawFromGrid, 0.0, ['n/a'], $socPercentBefore),
            productionKwh: $productionKwh,
            consumptionKwh: $consumptionKwh,
            priceKwh: $priceKwh,
            socPercentBefore: $socPercentBefore,
            sohPercentAfter: $sohPercentAfter,
        );
    }
}
