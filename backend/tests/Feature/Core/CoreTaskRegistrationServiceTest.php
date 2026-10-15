<?php

namespace Tests\Feature\Core;

use App\Models\CoreTask;
use App\Services\CoreTaskRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function emptyIdentityFieldsProvider(): array
    {
        return [
            'empty module key' => [
                '',
                'PICK_AND_DROP_REQUEST',
                'pick_and_drop_request',
                'request-empty-module',
            ],
            'empty task type' => [
                'PickAndDrop',
                '',
                'pick_and_drop_request',
                'request-empty-type',
            ],
            'empty source type' => [
                'PickAndDrop',
                'PICK_AND_DROP_REQUEST',
                '',
                'request-empty-source-type',
            ],
            'empty source id' => [
                'PickAndDrop',
                'PICK_AND_DROP_REQUEST',
                'pick_and_drop_request',
                '',
            ],
        ];
    }

    #[DataProvider('emptyIdentityFieldsProvider')]
    public function test_empty_identity_fields_are_rejected(
        string $moduleKey,
        string $taskType,
        string $sourceType,
        string $sourceId
    ): void {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->register(
            $moduleKey,
            $taskType,
            $sourceType,
            $sourceId
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

    public function test_identity_fields_are_trimmed_before_registration(): void
    {
        $task = $this->registry->register(
            ' PickAndDrop ',
            ' PICK_AND_DROP_REQUEST ',
            ' pick_and_drop_request ',
            ' request-trim-001 '
        );

        $this->assertSame('PickAndDrop', $task->module_key);
        $this->assertSame(
            'PICK_AND_DROP_REQUEST',
            $task->task_type
        );
        $this->assertSame(
            'pick_and_drop_request',
            $task->source_type
        );
        $this->assertSame('request-trim-001', $task->source_id);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'module_key' => 'PickAndDrop',
            'task_type' => 'PICK_AND_DROP_REQUEST',
            'source_id' => 'request-trim-001',
        ]);
    }

    public function test_created_by_account_id_is_stored_on_registration(): void
    {
        $accountId = '11111111-1111-4111-8111-111111111111';

        $task = $this->registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-creator-001',
            $accountId
        );

        $this->assertSame($accountId, $task->created_by_account_id);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'created_by_account_id' => $accountId,
        ]);
    }

    public function test_repeated_registration_does_not_overwrite_original_creator(): void
    {
        $originalCreator = '11111111-1111-4111-8111-111111111111';
        $secondCreator = '22222222-2222-4222-8222-222222222222';

        $first = $this->registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-creator-idempotent-001',
            $originalCreator
        );

        $second = $this->registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-creator-idempotent-001',
            $secondCreator
        );

        $this->assertSame($first->task_id, $second->task_id);
        $this->assertSame(
            $originalCreator,
            $second->created_by_account_id
        );
        $this->assertDatabaseCount('core_tasks', 1);
    }
}
