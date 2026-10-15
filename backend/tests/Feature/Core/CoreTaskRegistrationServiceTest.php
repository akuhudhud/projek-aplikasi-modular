<?php

namespace Tests\Feature\Core;

use App\Models\CoreTask;
use App\Services\CoreTaskRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CoreTaskRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CoreTaskRegistryService $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new CoreTaskRegistryService();
    }

    public function test_registered_module_task_type_can_create_core_task(): void
    {
        $task = $this->registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-001'
        );

        $this->assertInstanceOf(CoreTask::class, $task);
        $this->assertNotEmpty($task->task_id);
        $this->assertSame('PickAndDrop', $task->module_key);
        $this->assertSame(
            'PICK_AND_DROP_REQUEST',
            $task->task_type
        );
        $this->assertSame('request-001', $task->source_id);
        $this->assertSame(CoreTask::STATUS_OPEN, $task->generic_status);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'module_key' => 'PickAndDrop',
            'task_type' => 'PICK_AND_DROP_REQUEST',
            'source_id' => 'request-001',
        ]);
    }

    public function test_unknown_module_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->register(
            'UnknownModule',
            'UNKNOWN_TASK',
            'test_source',
            'source-001'
        );
    }

    public function test_unsupported_task_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->register(
            'PickAndDrop',
            'UNDECLARED_TASK',
            'pick_and_drop_request',
            'request-002'
        );
    }

    public function test_empty_source_identity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            ' '
        );
    }

    public function test_repeated_registration_returns_existing_task(): void
    {
        $first = $this->registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-idempotent-001'
        );

        $second = $this->registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'different_source_label',
            'request-idempotent-001'
        );

        $this->assertSame($first->task_id, $second->task_id);

        $this->assertDatabaseCount('core_tasks', 1);

        $this->assertSame(
            'pick_and_drop_request',
            $second->source_type
        );
    }

    public function test_different_source_ids_create_separate_tasks(): void
    {
        $first = $this->registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-101'
        );

        $second = $this->registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-102'
        );

        $this->assertNotSame($first->task_id, $second->task_id);
        $this->assertDatabaseCount('core_tasks', 2);
    }
}
