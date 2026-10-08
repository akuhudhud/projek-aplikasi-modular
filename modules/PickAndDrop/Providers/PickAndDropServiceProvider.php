<?php

namespace Modules\PickAndDrop\Providers;

use Illuminate\Support\ServiceProvider;

class PickAndDropServiceProvider extends ServiceProvider
{
    /**
     * Register module services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/module.php',
            'modules.pick_and_drop'
        );
    }

    /**
     * Bootstrap module services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(
            dirname(__DIR__) . '/Routes/api.php'
        );
    }
}
