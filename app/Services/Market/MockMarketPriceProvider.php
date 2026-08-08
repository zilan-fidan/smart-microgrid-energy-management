<?php

namespace App\Services\Market;

use App\Domain\Contracts\MarketPriceProviderInterface;

class MockMarketPriceProvider implements MarketPriceProviderInterface
{
    private const MIN_PRICE = 1.40;

    private const MAX_PRICE = 3.10;

    private const PEAK_HOUR = 19;

    /**
     * @var float[]|null
     */
    private ?array $prices = null;

    /**
     * Single national day-ahead price series (TL/kWh) — no zonal split.
     * Cosine curve peaking around 19:00, troughing near 07:00, plus a
     * small +-5% jitter, clipped to the configured band.
     *
     * Generated once and memoized: a "day" of prices must stay the same
     * series across every call within a run (e.g. every hour of a
     * simulation and the baseline calculator reading the same day), not a
     * fresh random draw each time.
     */
    public function getHourlyPrices(): array
    {
        if ($this->prices !== null) {
            return $this->prices;
        }

        $prices = [];
        $range = self::MAX_PRICE - self::MIN_PRICE;

        for ($hour = 0; $hour < 24; $hour++) {
            $phase = 2 * M_PI * ($hour - self::PEAK_HOUR) / 24;
            $normalized = (cos($phase) + 1) / 2;
            $base = self::MIN_PRICE + $normalized * $range;

            $jitter = 1 + (mt_rand(-5, 5) / 100);
            $price = $base * $jitter;

            $prices[$hour] = round(min(self::MAX_PRICE, max(self::MIN_PRICE, $price)), 2);
        }

        $this->prices = $prices;

        return $this->prices;
    }
}
