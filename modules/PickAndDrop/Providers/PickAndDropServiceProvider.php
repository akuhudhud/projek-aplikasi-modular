<?php

namespace Modules\PickAndDrop\Providers;

use Illuminate\Support\ServiceProvider;

class PickAndDropServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/module.php',
            'modules.pick_and_drop'
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(
            dirname(__DIR__) . '/Routes/api.php'
        );
    }
}
