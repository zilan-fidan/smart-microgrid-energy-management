<?php

namespace App\Services\Simulation;

use App\Domain\Contracts\MultiDaySimulationRunnerInterface;
use App\Domain\Contracts\SimulationRunnerInterface;
use App\Domain\Simulation\DailySimulation;
use InvalidArgumentException;

/**
 * Repeats the single-day SimulationRunner N times without duplicating any of
 * its per-hour logic (OCP: the 24-hour run is still a standalone call). The
 * only thing this layer adds is continuity between days.
 *
 * Day 1 starts from null → the single-day runner loads the real persisted
 * battery, exactly as a lone run would. Every later day starts from the
 * previous day's DailySimulation::$endingBattery:
 *
 *   - SOC: hour 23's resulting charge level becomes hour 0's starting level.
 *   - SOH: hour 23's cumulatively worn health becomes the next day's starting
 *     health, so SimpleCycleDegradationRule keeps chipping away from where it
 *     left off — wear is monotonic across the whole horizon, not reset nightly.
 *
 * Side-effect-free, same as the single-day runner: this class holds only a
 * SimulationRunnerInterface — no repository, no save/delete anywhere on the
 * code path. The battery threaded between days is an immutable in-memory
 * snapshot; the persisted JSON is never read for writing nor written.
 *
 * Production/consumption profiles are read from each asset's stored hourly
 * array, and MockMarketPriceProvider memoizes one price series per process,
 * so every simulated day sees the same "shape". This is deliberate: the model
 * is "one typical day repeated N times". (Wind, if configured, is the only
 * noisy input, but it too is frozen once generated onto the asset.) Per-day
 * weather/price variation would mean threading a day index through
 * HourlyAggregatorInterface and MarketPriceProviderInterface — an OCP break on
 * those contracts for a cosmetic gain — so it's intentionally out of scope.
 */
class MultiDaySimulationRunner implements MultiDaySimulationRunnerInterface
{
    public function __construct(
        private readonly SimulationRunnerInterface $dayRunner,
    ) {
    }

    public function runMultipleDays(int $days): array
    {
        if ($days < 1) {
            throw new InvalidArgumentException("Day count must be at least 1, got {$days}.");
        }

        $simulations = [];
        $carriedBattery = null;

        for ($day = 1; $day <= $days; $day++) {
            $daily = $this->dayRunner->runFullDay($carriedBattery);
            $simulations[] = $daily;

            // Feed this day's end state into the next day. On the last
            // iteration the assignment is simply unused.
            $carriedBattery = $daily->endingBattery;
        }

        return $simulations;
    }
}
