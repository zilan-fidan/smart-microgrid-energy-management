<?php

namespace Tests\Unit\Battery;

use App\Services\Battery\DegradationCostCalculator;
use App\Services\Battery\SimpleCycleDegradationRule;
use PHPUnit\Framework\TestCase;
use Tests\Support\BatteryFactory;

class DegradationCostCalculatorTest extends TestCase
{
    /**
     * Hand calculation: capacity 100 kWh, replacement 20,000 TL.
     * SOH budget to end-of-life = 100 - 80 = 20 percentage points.
     * Lifetime full cycles = 20 / 0.05 = 400.
     * Lifetime cycled kWh = 400 * 100 = 40,000 kWh.
     * Cost per kWh = 20,000 / 40,000 = 0.5 TL/kWh.
     */
    public function test_cost_per_kwh_matches_hand_calculation(): void
    {
        $battery = BatteryFactory::make([
            'capacity_kwh' => 100.0,
            'replacement_cost_tl' => 20_000.0,
        ]);

        $costPerKwh = (new DegradationCostCalculator())->costPerKwh($battery);

        $this->assertEqualsWithDelta(0.5, $costPerKwh, 0.0001);
    }

    /**
     * Doubling capacity (with the same replacement cost) halves the cost per
     * kWh cycled — twice the lifetime cycled kWh for the same money.
     */
    public function test_cost_per_kwh_scales_inversely_with_capacity(): void
    {
        $battery = BatteryFactory::make([
            'capacity_kwh' => 200.0,
            'replacement_cost_tl' => 20_000.0,
        ]);

        $costPerKwh = (new DegradationCostCalculator())->costPerKwh($battery);

        $this->assertEqualsWithDelta(0.25, $costPerKwh, 0.0001);
    }

    public function test_cost_per_kwh_is_zero_when_replacement_cost_is_unset(): void
    {
        $battery = BatteryFactory::make(['replacement_cost_tl' => 0.0]);

        $this->assertSame(0.0, (new DegradationCostCalculator())->costPerKwh($battery));
    }

    public function test_end_of_life_threshold_and_degradation_rate_stay_in_sync(): void
    {
        // Not a duplicated magic number: DegradationCostCalculator reuses
        // SimpleCycleDegradationRule's rate directly, so this just confirms
        // the constant it reads from is the one degradation actually uses.
        $this->assertSame(0.05, SimpleCycleDegradationRule::DEGRADATION_PER_FULL_CYCLE);
        $this->assertSame(80.0, DegradationCostCalculator::END_OF_LIFE_SOH_PERCENT);
    }
}
