<?php

namespace Tests\Feature\Modules\PickAndDrop;

use App\Providers\ModuleDiscoveryServiceProvider;
use Modules\PickAndDrop\Providers\PickAndDropServiceProvider;
use Tests\TestCase;

class PickAndDropModuleFoundationTest extends TestCase
{
    public function test_generic_module_discovery_provider_is_registered(): void
    {
        $providers = config('app.providers', []);

        $this->assertContains(
            ModuleDiscoveryServiceProvider::class,
            $providers
        );
    }

    public function test_pick_and_drop_is_disabled_in_module_metadata(): void
    {
        $module = require base_path(
            '../modules/PickAndDrop/Config/module.php'
        );

        $this->assertSame('PickAndDrop', $module['name']);
        $this->assertFalse($module['enabled']);
    }

    public function test_disabled_pick_and_drop_provider_is_not_loaded(): void
    {
        $providers = app()->getLoadedProviders();

        $this->assertFalse(
            $providers[PickAndDropServiceProvider::class] ?? false
        );
    }

    public function test_core_configuration_does_not_directly_register_pick_and_drop_provider(): void
    {
        $providers = config('app.providers', []);

        $this->assertNotContains(
            PickAndDropServiceProvider::class,
            $providers
        );
    }

    public function test_pick_and_drop_foundation_endpoint_is_not_registered(): void
    {
        $this->getJson('/api/pick-and-drop/foundation/status')
            ->assertNotFound();
    }

    public function test_pick_and_drop_request_endpoint_is_not_registered(): void
    {
        $this->postJson('/api/pick-and-drop/requests', [])
            ->assertNotFound();
    }
}
