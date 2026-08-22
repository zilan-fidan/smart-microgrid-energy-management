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

    /**
     * "If I store now, what will I likely sell it for later?" — the 75th
     * percentile of the remaining hours' prices (hour > current hour).
     * Deliberately not the max: the max is a single spike hour that the
     * simulation isn't guaranteed to actually hit (SellSurplusRule only
     * fires when there's surplus that hour), so betting the whole
     * breakeven decision on it would overstate the expected payoff. The
     * 75th percentile is "a good hour, not a lucky one" — a realistic
     * upside estimate. Falls back to the whole day's 75th percentile when
     * there are no hours left (end of day), since "the rest of today" is
     * empty by definition at hour 23.
     */
    public function getExpectedSellPrice(): float
    {
        $remaining = [];
        foreach ($this->hourlyPrices as $hour => $price) {
            if ($hour > $this->hour) {
                $remaining[] = $price;
            }
        }

        $pool = $remaining !== [] ? $remaining : array_values($this->hourlyPrices);

        if ($pool === []) {
            return $this->priceKwh;
        }

        sort($pool);

        return $this->percentile($pool, 75);
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

    /**
     * @param  float[]  $sortedValues  Must already be sorted ascending.
     */
    private function percentile(array $sortedValues, float $percentile): float
    {
        $count = count($sortedValues);

        if ($count === 1) {
            return $sortedValues[0];
        }

        $rank = ($percentile / 100) * ($count - 1);
        $lowerIndex = (int) floor($rank);
        $upperIndex = (int) ceil($rank);

        if ($lowerIndex === $upperIndex) {
            return $sortedValues[$lowerIndex];
        }

        $fraction = $rank - $lowerIndex;

        return $sortedValues[$lowerIndex] + ($sortedValues[$upperIndex] - $sortedValues[$lowerIndex]) * $fraction;
    }
}
