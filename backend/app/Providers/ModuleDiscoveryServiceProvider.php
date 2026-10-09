<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Throwable;

class ModuleDiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $moduleConfigFiles = glob(
            base_path('../modules/*/Config/module.php')
        );

        if ($moduleConfigFiles === false) {
            return;
        }

        sort($moduleConfigFiles);

        foreach ($moduleConfigFiles as $moduleConfigFile) {
            try {
                $module = require $moduleConfigFile;
            } catch (Throwable $exception) {
                continue;
            }

            if (! is_array($module)) {
                continue;
            }

            if (($module['enabled'] ?? false) !== true) {
                continue;
            }

            $providerClass = $module['provider'] ?? null;

            if (
                ! is_string($providerClass)
                || ! class_exists($providerClass)
                || ! is_subclass_of($providerClass, ServiceProvider::class)
            ) {
                continue;
            }

            if (isset($this->app->getLoadedProviders()[$providerClass])) {
                continue;
            }

            $this->app->register($providerClass);
        }
    }
}
