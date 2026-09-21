<?php

namespace App\Domain\Contracts;

use App\Domain\Simulation\DailySimulation;

interface MultiDaySimulationRunnerInterface
{
    /**
     * Runs the 24-hour "what-if" simulation $days times in a row, threading
     * each day's ending battery snapshot (SOC + worn SOH) forward as the next
     * day's starting point.
     *
     * Same guarantee as the single-day runner: no persisted asset data is
     * ever mutated. The battery state carried between days is an in-memory
     * immutable value object only.
     *
     * @return DailySimulation[] One element per simulated day, ordered day 1
     *                           first through day $days last.
     */
    public function runMultipleDays(int $days): array;
}
