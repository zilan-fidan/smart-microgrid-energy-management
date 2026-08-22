<?php

namespace App\Services\Battery;

use App\Domain\Assets\Battery;

/**
 * Converts cycle wear (SimpleCycleDegradationRule's SOH-per-cycle rate) into
 * a TL/kWh cost. Kept separate from Battery (a physical-state value object)
 * and from SimpleCycleDegradationRule (which only knows physics, not money)
 * — this is the one place that turns "how fast does it wear" + "what does a
 * replacement cost" into "what does wearing it by 1 kWh cost", mirroring how
 * BaselineCostCalculator already lives apart from the domain objects it
 * prices.
 */
class DegradationCostCalculator
{
    /**
     * SOH% below which the battery is considered end-of-life. Real packs
     * aren't used down to 0% SOH — 80% is a common industry rule of thumb
     * for "no longer fit for its original duty".
     */
    public const END_OF_LIFE_SOH_PERCENT = 80.0;

    /**
     * Amortized wear cost of cycling 1 kWh through this battery, in TL.
     * Spread evenly across every kWh the battery will ever cycle between
     * a fresh 100% SOH and the end-of-life threshold — not adjusted for
     * the battery's current SOH, so this is a constant "cost per kWh
     * cycled" for the battery's whole service life, not just what's left.
     */
    public function costPerKwh(Battery $battery): float
    {
        if ($battery->getReplacementCostTl() <= 0.0 || $battery->getCapacityKwh() <= 0.0) {
            return 0.0;
        }

        $sohBudgetPercent = 100.0 - self::END_OF_LIFE_SOH_PERCENT;
        $lifetimeFullCycles = $sohBudgetPercent / SimpleCycleDegradationRule::DEGRADATION_PER_FULL_CYCLE;
        $lifetimeCycledKwh = $lifetimeFullCycles * $battery->getCapacityKwh();

        return $battery->getReplacementCostTl() / $lifetimeCycledKwh;
    }
}
