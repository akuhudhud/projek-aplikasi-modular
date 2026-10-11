<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
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
            $moduleName = basename(
                dirname(dirname($moduleConfigFile))
            );

            try {
                $module = require $moduleConfigFile;
            } catch (Throwable $exception) {
                Log::warning(
                    'Module discovery skipped unreadable module metadata.',
                    [
                        'module' => $moduleName,
                        'error_type' => get_class($exception),
                    ]
                );

                continue;
            }

            if (! is_array($module)) {
                Log::warning(
                    'Module discovery skipped non-array module metadata.',
                    [
                        'module' => $moduleName,
                    ]
                );

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
                Log::warning(
                    'Module discovery skipped invalid provider declaration.',
                    [
                        'module' => $moduleName,
                        'provider' => is_string($providerClass)
                            ? $providerClass
                            : get_debug_type($providerClass),
                    ]
                );

                continue;
            }

            if (isset($this->app->getLoadedProviders()[$providerClass])) {
                continue;
            }

            $this->app->register($providerClass);
        }
    }
}
