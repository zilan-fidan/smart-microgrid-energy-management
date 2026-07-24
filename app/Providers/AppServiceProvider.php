<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
