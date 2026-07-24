<?php

namespace App\Domain\Contracts;

use App\Domain\Simulation\DailySimulation;

interface SimulationRunnerInterface
{
    /**
     * Runs a full 24-hour "what-if" simulation. Must not mutate any
     * persisted asset data — the battery's stored SOC is untouched.
     */
    public function runFullDay(): DailySimulation;
}
