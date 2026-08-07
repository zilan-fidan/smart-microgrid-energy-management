<?php

namespace Tests\Support;

use App\Domain\Assets\Battery;

class BatteryFactory
{
    public static function make(array $overrides = []): Battery
    {
        return Battery::fromArray(array_merge([
            'id' => 'battery-test',
            'name' => 'Test Battery',
            'capacity_kwh' => 100.0,
            'soc_percent' => 50.0,
            'min_soc' => 10.0,
            'max_soc' => 90.0,
            'efficiency_rate' => 0.9,
            'soh_percent' => 100.0,
        ], $overrides));
    }
}
