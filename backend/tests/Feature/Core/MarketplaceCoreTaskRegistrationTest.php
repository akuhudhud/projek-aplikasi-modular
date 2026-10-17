<?php

namespace Tests\Feature\Core;

use App\Models\CoreTask;
use App\Services\CoreTaskRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MarketplaceCoreTaskRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_task_can_be_registered_using_core_configuration(): void
    {
        $registry = new CoreTaskRegistryService();

        $task = $registry->register(
            'Marketplace',
            'MARKETPLACE_SERVICE_REQUEST',
            'marketplace_request',
            'request-marketplace-001'
        );

        $this->assertInstanceOf(CoreTask::class, $task);
        $this->assertSame('Marketplace', $task->module_key);
        $this->assertSame(
            'MARKETPLACE_SERVICE_REQUEST',
            $task->task_type
        );
        $this->assertSame(
            'marketplace_request',
            $task->source_type
        );
        $this->assertSame(
            'request-marketplace-001',
            $task->source_id
        );
        $this->assertSame(
            CoreTask::STATUS_OPEN,
            $task->generic_status
        );

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'module_key' => 'Marketplace',
            'task_type' => 'MARKETPLACE_SERVICE_REQUEST',
            'source_id' => 'request-marketplace-001',
        ]);
    }

    public function test_marketplace_rejects_unregistered_task_type(): void
    {
        $registry = new CoreTaskRegistryService();

        $this->expectException(InvalidArgumentException::class);

        $registry->register(
            'Marketplace',
            'UNREGISTERED_TASK',
            'marketplace_request',
            'request-marketplace-invalid'
        );
    }

    public function test_marketplace_registration_is_idempotent(): void
    {
        $registry = new CoreTaskRegistryService();

        $first = $registry->register(
            'Marketplace',
            'MARKETPLACE_SERVICE_REQUEST',
            'marketplace_request',
            'request-marketplace-retry'
        );

        $second = $registry->register(
            'Marketplace',
            'MARKETPLACE_SERVICE_REQUEST',
            'marketplace_request',
            'request-marketplace-retry'
        );

        $this->assertSame($first->task_id, $second->task_id);

        $this->assertDatabaseCount('core_tasks', 1);
    }
}
