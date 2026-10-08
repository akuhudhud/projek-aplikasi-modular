<?php

namespace Tests\Feature\Modules\PickAndDrop;

use Modules\PickAndDrop\Providers\PickAndDropServiceProvider;
use Tests\TestCase;

class PickAndDropModuleFoundationTest extends TestCase
{
    public function test_pick_and_drop_service_provider_is_loaded(): void
    {
        $this->assertTrue(
            app()->getLoadedProviders()[PickAndDropServiceProvider::class] ?? false
        );
    }

    public function test_pick_and_drop_module_configuration_is_available(): void
    {
        $this->assertSame(
            'PickAndDrop',
            config('modules.pick_and_drop.name')
        );

        $this->assertSame(
            '0.1.0',
            config('modules.pick_and_drop.version')
        );

        $this->assertTrue(
            config('modules.pick_and_drop.enabled')
        );
    }

    public function test_pick_and_drop_foundation_status_endpoint_is_available(): void
    {
        $response = $this->getJson('/api/pick-and-drop/foundation/status');

        $response
            ->assertOk()
            ->assertJson([
                'module' => 'PickAndDrop',
                'version' => '0.1.0',
                'status' => 'ready',
            ]);
    }
}
