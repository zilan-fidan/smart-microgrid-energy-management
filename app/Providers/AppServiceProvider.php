<?php

namespace App\Providers;

use App\Domain\Contracts\AssetRepositoryInterface;
use App\Repositories\JsonAssetRepository;
use App\Services\Storage\JsonFileStorage;
use App\Services\Storage\JsonStorageInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JsonStorageInterface::class, function () {
            return new JsonFileStorage(
                Storage::disk('microgrid'),
                'microgrid.json',
            );
        });

        $this->app->singleton(AssetRepositoryInterface::class, function ($app) {
            return new JsonAssetRepository($app->make(JsonStorageInterface::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
