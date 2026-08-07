<?php

namespace Tests\Support;

use App\Domain\Assets\Battery;
use App\Domain\Decision\DecisionContext;

class DecisionContextFactory
{
    /**
     * @param  float[]|null  $hourlyPrices  Full 24h price list. Defaults to a flat
     *                                      2.0 TL/kWh day with $priceKwh injected at
     *                                      $hour, so isPriceLow()/isPriceHigh() behave
     *                                      predictably off a single override.
     */
    public static function make(
        int $hour = 12,
        float $productionKwh = 0.0,
        float $consumptionKwh = 0.0,
        float $priceKwh = 2.0,
        ?Battery $battery = null,
        ?array $hourlyPrices = null,
    ): DecisionContext {
        $prices = $hourlyPrices ?? array_fill(0, 24, 2.0);
        $prices[$hour] = $priceKwh;

        return new DecisionContext(
            $hour,
            $productionKwh,
            $consumptionKwh,
            $priceKwh,
            $battery ?? BatteryFactory::make(),
            $prices,
        );
    }
}
