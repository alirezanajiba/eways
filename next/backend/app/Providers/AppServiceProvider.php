<?php

namespace App\Providers;

use App\Services\EwaysApiService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EwaysApiService::class, fn () => new EwaysApiService(
            (string) config('services.eways.base_url'),
            (string) config('services.eways.version', '1'),
        ));
    }

    public function boot(): void
    {
    }
}
