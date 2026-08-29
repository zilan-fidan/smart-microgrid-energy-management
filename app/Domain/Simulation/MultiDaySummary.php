<?php

namespace App\Domain\Simulation;

use App\Services\Simulation\BaselineCostCalculator;

/**
 * Investment-payback view over a multi-day run.
 *
 * Mental model: the battery's replacementCostTl is a one-off "investment".
 * Each day the battery earns some of it back — that day's savingsTl, i.e.
 * how much cheaper the day was than the no-battery baseline. Once the
 * running total of those savings clears the investment, the battery has
 * "paid for itself".
 *
 * Built the same way DashboardMetrics is: a static factory that derives
 * everything from an array of DailySimulation — the runner never does this
 * arithmetic itself (SRP).
 */
class MultiDaySummary
{
    /**
     * @param  float[]  $dailySavingsTl  savingsTl for each simulated day, in order.
     * @param  float[]  $cumulativeSavingsTl  Running total of dailySavingsTl, day by day.
     * @param  float  $totalInvestmentTl  The battery's replacementCostTl.
     * @param  int|null  $estimatedPaybackDays  1-based day the cumulative savings
     *                                          first reach the investment. If that
     *                                          never happens within the simulated
     *                                          horizon, a linear projection from the
     *                                          mean daily saving (an int beyond the
     *                                          horizon); null if the mean daily
     *                                          saving is <= 0 (never pays back).
     */
    public function __construct(
        public readonly array $dailySavingsTl,
        public readonly array $cumulativeSavingsTl,
        public readonly float $totalInvestmentTl,
        public readonly ?int $estimatedPaybackDays,
    ) {
    }

    /**
     * @param  DailySimulation[]  $dailySimulations  Ordered day 1 .. day N.
     */
    public static function fromDailySimulations(
        array $dailySimulations,
        BaselineCostCalculator $baselineCostCalculator,
    ): self {
        $dailySavings = array_map(
            fn (DailySimulation $daily) => DashboardMetrics::fromDailySimulation($daily, $baselineCostCalculator)->savingsTl,
            array_values($dailySimulations),
        );

        $cumulative = [];
        $runningTotal = 0.0;
        foreach ($dailySavings as $saving) {
            $runningTotal += $saving;
            $cumulative[] = round($runningTotal, 2);
        }

        $investment = $dailySimulations === []
            ? 0.0
            : ($dailySimulations[array_key_first($dailySimulations)]->endingBattery?->getReplacementCostTl() ?? 0.0);

        return new self(
            dailySavingsTl: $dailySavings,
            cumulativeSavingsTl: $cumulative,
            totalInvestmentTl: round($investment, 2),
            estimatedPaybackDays: self::estimatePaybackDays($dailySavings, $cumulative, $investment),
        );
    }

    /**
     * @param  float[]  $dailySavings
     * @param  float[]  $cumulative
     */
    private static function estimatePaybackDays(array $dailySavings, array $cumulative, float $investment): ?int
    {
        foreach ($cumulative as $index => $total) {
            if ($total >= $investment) {
                return $index + 1;
            }
        }

        // Not paid back inside the simulated window — extrapolate linearly
        // from the average daily saving. Deliberately crude: no compounding,
        // no seasonality.
        $dayCount = count($dailySavings);
        if ($dayCount === 0) {
            return null;
        }

        $meanDailySaving = array_sum($dailySavings) / $dayCount;
        if ($meanDailySaving <= 0.0) {
            return null;
        }

        return (int) ceil($investment / $meanDailySaving);
    }
}
