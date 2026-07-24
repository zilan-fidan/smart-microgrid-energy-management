<?php

namespace App\Domain\Simulation;

class DashboardMetrics
{
    /**
     * @param  array<int, array{hour:int, soc:float, production:float, consumption:float, price:float}>  $hourlyBreakdown
     */
    public function __construct(
        public readonly float $totalProductionKwh,
        public readonly float $totalConsumptionKwh,
        public readonly float $totalStoredKwh,
        public readonly float $totalSoldKwh,
        public readonly float $totalGridDrawKwh,
        public readonly float $totalLossKwh,
        public readonly array $hourlyBreakdown,
    ) {
    }

    public static function fromDailySimulation(DailySimulation $simulation): self
    {
        $totalProduction = array_sum(array_map(fn (SimulationResult $r) => $r->productionKwh, $simulation->results));
        $totalConsumption = array_sum(array_map(fn (SimulationResult $r) => $r->consumptionKwh, $simulation->results));

        $hourlyBreakdown = array_map(fn (SimulationResult $r) => [
            'hour' => $r->hour,
            'soc' => round($r->decision->resultingSocPercent, 1),
            'production' => round($r->productionKwh, 2),
            'consumption' => round($r->consumptionKwh, 2),
            'price' => round($r->priceKwh, 2),
        ], $simulation->results);

        return new self(
            totalProductionKwh: round($totalProduction, 2),
            totalConsumptionKwh: round($totalConsumption, 2),
            totalStoredKwh: round($simulation->totalStoredKwh(), 2),
            totalSoldKwh: round($simulation->totalSoldKwh(), 2),
            totalGridDrawKwh: round($simulation->totalDrawnFromGridKwh(), 2),
            totalLossKwh: round($simulation->totalLossKwh(), 2),
            hourlyBreakdown: $hourlyBreakdown,
        );
    }
}
