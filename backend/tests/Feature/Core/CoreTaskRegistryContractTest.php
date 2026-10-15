<?php

namespace Tests\Feature\Core;

use App\Contracts\CoreTaskRegistry;
use App\Services\CoreTaskRegistryService;
use Tests\TestCase;

class CoreTaskRegistryContractTest extends TestCase
{
    public function test_core_task_registry_contract_resolves_to_service(): void
    {
        $registry = $this->app->make(CoreTaskRegistry::class);

        $this->assertInstanceOf(
            CoreTaskRegistryService::class,
            $registry
        );
    }

    public function test_registry_service_implements_core_contract(): void
    {
        $service = new CoreTaskRegistryService();

        $this->assertInstanceOf(
            CoreTaskRegistry::class,
            $service
        );
    }
}
