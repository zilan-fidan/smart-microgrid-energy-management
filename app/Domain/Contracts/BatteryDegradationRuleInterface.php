<?php

namespace App\Domain\Contracts;

use App\Domain\Assets\Battery;

interface BatteryDegradationRuleInterface
{
    /**
     * Returns a new Battery snapshot with SOH reduced to reflect the wear
     * from cycling $cycledKwh through the battery. Immutable — never
     * mutates $battery, and never touches persisted data.
     */
    public function applyDegradation(Battery $battery, float $cycledKwh): Battery;
}
