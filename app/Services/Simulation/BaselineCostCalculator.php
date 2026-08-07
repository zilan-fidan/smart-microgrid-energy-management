<?php

namespace App\Services\Simulation;

use App\Domain\Simulation\DailySimulation;
use App\Domain\Simulation\SimulationResult;

/**
 * "What if there were no battery?" baseline: every hour's raw
 * production/consumption gap is settled directly against the market —
 * deficits bought from the grid, surpluses sold — at that hour's price.
 */
class BaselineCostCalculator
{
    public function calculate(DailySimulation $simulation, array $hourlyPrices): float
    {
        return array_sum(array_map(
            function (SimulationResult $r) use ($hourlyPrices) {
                $price = $hourlyPrices[$r->hour] ?? $r->priceKwh;
                $net = $r->productionKwh - $r->consumptionKwh;

                return $net < 0
                    ? abs($net) * $price
                    : -($net * $price);
            },
            $simulation->results,
        ));
    }
}
