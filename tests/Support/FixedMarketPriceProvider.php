<?php

namespace Tests\Support;

use App\Domain\Contracts\MarketPriceProviderInterface;

class FixedMarketPriceProvider implements MarketPriceProviderInterface
{
    /**
     * @param  float[]  $prices  24 TL/kWh values indexed 0-23.
     */
    public function __construct(private readonly array $prices)
    {
    }

    public function getHourlyPrices(): array
    {
        return $this->prices;
    }
}
