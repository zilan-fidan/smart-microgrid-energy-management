<?php

namespace App\Domain\Decision;

use App\Domain\Assets\Battery;

class DecisionContext
{
    /**
     * @param  float[]  $hourlyPrices  24 TL/kWh values for the day, used to
     *                                 classify $priceKwh as low/high relative
     *                                 to the day's median (no hardcoded threshold)
     */
    public function __construct(
        public readonly int $hour,
        public readonly float $totalProductionKwh,
        public readonly float $totalConsumptionKwh,
        public readonly float $priceKwh,
        public readonly Battery $battery,
        private readonly array $hourlyPrices,
    ) {
    }

    public function netSurplusOrDeficit(): float
    {
        return $this->totalProductionKwh - $this->totalConsumptionKwh;
    }

    public function isPriceLow(): bool
    {
        return $this->priceKwh <= $this->medianPrice();
    }

    public function isPriceHigh(): bool
    {
        return ! $this->isPriceLow();
    }

    private function medianPrice(): float
    {
        if ($this->hourlyPrices === []) {
            return $this->priceKwh;
        }

        $sorted = $this->hourlyPrices;
        sort($sorted);
        $count = count($sorted);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($sorted[$middle - 1] + $sorted[$middle]) / 2
            : $sorted[$middle];
    }
}
