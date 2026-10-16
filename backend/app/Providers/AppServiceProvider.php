<?php

namespace App\Providers;

use App\Contracts\CoreTaskAssigner;
use App\Contracts\CoreTaskLifecycle;
use App\Contracts\CoreTaskRegistry;
use App\Services\CoreTaskAssignmentService;
use App\Services\CoreTaskLifecycleService;
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

        $this->app->singleton(
            CoreTaskAssigner::class,
            CoreTaskAssignmentService::class
        );

        $this->app->singleton(
            CoreTaskLifecycle::class,
            CoreTaskLifecycleService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
