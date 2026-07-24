<?php

namespace App\Services\Generators;

use App\Domain\Contracts\HourlyProfileGeneratorInterface;

class WindProfileGenerator implements HourlyProfileGeneratorInterface
{
    private const BASE_LOAD = 0.35;

    private const NOISE_BAND = 0.25;

    /**
     * Returns a noisy fraction of nameplate capacity (0-1) — wind has
     * no clean diurnal shape, so each call differs within a bounded band.
     * Multiply by capacity_kw to get kWh.
     */
    public function generate(): array
    {
        $profile = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $noise = (mt_rand(-100, 100) / 100) * self::NOISE_BAND;
            $profile[$hour] = round(min(1.0, max(0.0, self::BASE_LOAD + $noise)), 4);
        }

        return $profile;
    }
}
