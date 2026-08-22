<?php

namespace Tests\Support;

use App\Domain\Assets\Battery;

class BatteryFactory
{
    /**
     * TL/kWh nameplate multiplier used to derive a default replacement_cost_tl
     * when the caller doesn't override it — mirrors AssetService::suggestedReplacementCostTl(),
     * scaled by capacity_kwh so the resulting degradation cost/kWh is constant
     * (~0.25 TL/kWh, see DegradationCostCalculator) regardless of which
     * capacity_kwh a given test overrides.
     */
    private const DEFAULT_REPLACEMENT_COST_TL_PER_KWH = 100.0;

    public static function make(array $overrides = []): Battery
    {
        $defaults = [
            'id' => 'battery-test',
            'name' => 'Test Battery',
            'capacity_kwh' => 100.0,
            'soc_percent' => 50.0,
            'min_soc' => 10.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.9,
            'soh_percent' => 100.0,
        ];

        $merged = array_merge($defaults, $overrides);

        if (! array_key_exists('replacement_cost_tl', $overrides)) {
            $merged['replacement_cost_tl'] = $merged['capacity_kwh'] * self::DEFAULT_REPLACEMENT_COST_TL_PER_KWH;
        }

        return Battery::fromArray($merged);
    }
}
