<?php

namespace App\Services\Battery;

use App\Domain\Assets\Battery;
use App\Domain\Contracts\BatteryDegradationRuleInterface;

class SimpleCycleDegradationRule implements BatteryDegradationRuleInterface
{
    /**
     * SOH lost per 1.0 equivalent full cycle (cycledKwh == capacityKwh).
     * 0.05%/cycle ~ 80% SOH after ~400 full cycles, a plausible order of
     * magnitude for consumer/commercial Li-ion — small enough per hour to
     * be invisible, but visibly adds up over a multi-day/what-if demo.
     */
    private const DEGRADATION_PER_FULL_CYCLE = 0.05;

    public function applyDegradation(Battery $battery, float $cycledKwh): Battery
    {
        if ($cycledKwh <= 0.0 || $battery->getCapacityKwh() <= 0.0) {
            return $battery;
        }

        $fullCycles = $cycledKwh / $battery->getCapacityKwh();
        $sohLoss = $fullCycles * self::DEGRADATION_PER_FULL_CYCLE;
        $newSoh = max(0.0, $battery->getSohPercent() - $sohLoss);

        return Battery::fromArray(array_merge($battery->toArray(), [
            'soh_percent' => $newSoh,
        ]));
    }
}
