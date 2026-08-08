<?php

namespace App\Services\Generators;

use App\Domain\Contracts\HourlyProfileGeneratorInterface;

class ConsumptionProfileGenerator implements HourlyProfileGeneratorInterface
{
    private const MORNING_PEAK_HOUR = 8;

    private const EVENING_PEAK_HOUR = 19;

    private const BASE_LOAD = 0.4;

    /**
     * Returns a two-peak (morning + evening) demand shape normalized to
     * a mean of 1.0, so multiplying by average_demand_kwh reproduces
     * that average across the day while keeping the daily pattern.
     */
    public function generate(): array
    {
        $raw = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $morning = exp(-(($hour - self::MORNING_PEAK_HOUR) ** 2) / 8);
            $evening = exp(-(($hour - self::EVENING_PEAK_HOUR) ** 2) / 8);
            $raw[$hour] = self::BASE_LOAD + $morning + $evening;
        }

        $mean = array_sum($raw) / count($raw);

        return array_map(fn ($value) => round($value / $mean, 4), $raw);
    }
}
