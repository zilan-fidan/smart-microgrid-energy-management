<?php

namespace App\Providers;

use App\Domain\Contracts\DecisionEngineInterface;
use App\Services\Decision\DecisionEngine;
use App\Services\Decision\Rules\DrawFromGridRule;
use App\Services\Decision\Rules\SellSurplusRule;
use App\Services\Decision\Rules\SocLimitGuardRule;
use App\Services\Decision\Rules\StoreSurplusRule;
use App\Services\Decision\Rules\UseBatteryRule;
use Illuminate\Support\ServiceProvider;

class DecisionServiceProvider extends ServiceProvider
{
    private const RULES = [
        SocLimitGuardRule::class,
        StoreSurplusRule::class,
        SellSurplusRule::class,
        UseBatteryRule::class,
        DrawFromGridRule::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag(self::RULES, 'decision.rules');

        $this->app->singleton(DecisionEngineInterface::class, function ($app) {
            return new DecisionEngine(iterator_to_array($app->tagged('decision.rules')));
        });
    }
}
