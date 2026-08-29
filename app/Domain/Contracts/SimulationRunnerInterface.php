<?php

namespace App\Domain\Contracts;

use App\Domain\Assets\Battery;
use App\Domain\Simulation\DailySimulation;

interface SimulationRunnerInterface
{
    /**
     * Runs a full 24-hour "what-if" simulation. Must not mutate any
     * persisted asset data — the battery's stored SOC is untouched.
     *
     * @param  Battery|null  $startingBattery  Optional in-memory snapshot to
     *                                         begin the day from. Null (the
     *                                         default) loads the real persisted
     *                                         battery as hour 0's starting point,
     *                                         exactly as before — a single-day
     *                                         run stays a standalone call. A
     *                                         non-null snapshot lets a caller
     *                                         (e.g. MultiDaySimulationRunner)
     *                                         thread the previous day's ending
     *                                         SOC/SOH forward without ever
     *                                         touching stored data.
     */
    public function runFullDay(?Battery $startingBattery = null): DailySimulation;
}
