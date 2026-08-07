<?php

namespace App\Providers;

use App\Domain\Contracts\BatteryDegradationRuleInterface;
use App\Domain\Contracts\HourlyAggregatorInterface;
use App\Domain\Contracts\SimulationRunnerInterface;
use App\Services\Aggregation\HourlyAggregator;
use App\Services\Battery\SimpleCycleDegradationRule;
use App\Services\Simulation\SimulationRunner;
use Illuminate\Support\ServiceProvider;

class SimulationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HourlyAggregatorInterface::class, HourlyAggregator::class);
        $this->app->singleton(BatteryDegradationRuleInterface::class, SimpleCycleDegradationRule::class);
        $this->app->singleton(SimulationRunnerInterface::class, SimulationRunner::class);
    }
}
