<?php

namespace Tests\Feature\Modules\PickAndDrop;

use Modules\PickAndDrop\Providers\PickAndDropServiceProvider;
use Tests\TestCase;

class PickAndDropModuleFoundationTest extends TestCase
{
    public function test_pick_and_drop_service_provider_can_be_resolved(): void
    {
        $provider = app()->resolve(PickAndDropServiceProvider::class);

        $this->assertInstanceOf(
            PickAndDropServiceProvider::class,
            $provider
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
}
