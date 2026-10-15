<?php

namespace App\Providers;

use App\Contracts\CoreTaskRegistry;
use App\Services\CoreTaskRegistryService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CoreTaskRegistry::class,
            CoreTaskRegistryService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
