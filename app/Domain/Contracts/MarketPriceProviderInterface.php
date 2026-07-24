<?php

namespace App\Domain\Contracts;

interface MarketPriceProviderInterface
{
    /**
     * @return float[] TL/kWh, 24 values indexed 0-23.
     */
    public function getHourlyPrices(): array;
}
