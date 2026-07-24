<?php

namespace App\Services\Generators;

use App\Domain\Contracts\HourlyProfileGeneratorInterface;

class SolarProfileGenerator implements HourlyProfileGeneratorInterface
{
    private const SUNRISE_HOUR = 6;

    private const SUNSET_HOUR = 19;

    /**
     * Returns a bell curve as a fraction of nameplate capacity (0-1),
     * peaking near solar noon. Multiply by capacity_kw to get kWh.
     */
    public function generate(): array
    {
        $profile = [];
        $daylightHours = self::SUNSET_HOUR - self::SUNRISE_HOUR;

        for ($hour = 0; $hour < 24; $hour++) {
            if ($hour < self::SUNRISE_HOUR || $hour >= self::SUNSET_HOUR) {
                $profile[$hour] = 0.0;

                continue;
            }

            $position = ($hour - self::SUNRISE_HOUR) / $daylightHours;
            $profile[$hour] = round(max(0.0, sin(M_PI * $position)), 4);
        }

        return $profile;
    }
}
