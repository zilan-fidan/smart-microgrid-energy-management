<?php

namespace App\Domain\Contracts;

use App\Domain\Assets\Battery;
use App\Domain\Decision\DecisionContext;

interface HourlyAggregatorInterface
{
    /**
     * Builds the DecisionContext for a given hour by summing all
     * production/consumption assets and looking up that hour's price.
     *
     * @param  Battery|null  $batterySnapshot  Use this snapshot instead of the
     *                                         persisted battery — lets a simulation
     *                                         thread an evolving SOC across hours
     *                                         without touching stored data.
     */
    public function aggregate(int $hour, ?Battery $batterySnapshot = null): DecisionContext;
}
