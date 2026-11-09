<?php

namespace Tests\Feature\Core;

use App\Contracts\CoreTaskRegistry;
use App\Models\CoreTask;
use App\Services\CoreTaskRegistryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

        config([
            'core_task_types.TestModule' => [
                'enabled' => true,
                'task_types' => [
                    'TEST_TASK',
                    'TEST_TASK_ALPHA',
                    'TEST_TASK_BETA',
                ],
            ],
        ]);
    }

    public function test_registry_contract_resolves_to_service(): void
    {
        $this->assertInstanceOf(
            CoreTaskRegistryService::class,
            $this->app->make(CoreTaskRegistry::class)
        );

        $this->assertInstanceOf(
            CoreTaskRegistry::class,
            $this->registry
        );
    }

    public function test_core_tasks_table_contains_required_fields(): void
    {
        $this->assertTrue(Schema::hasTable('core_tasks'));

        $this->assertTrue(Schema::hasColumns('core_tasks', [
            'task_id',
            'module_key',
            'task_type',
            'source_type',
            'source_id',
            'created_by_account_id',
            'assigned_account_id',
            'generic_status',
            'assigned_at',
            'started_at',
            'completed_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_new_registry_record_defaults_to_open_status(): void
    {
        $taskId = (string) Str::uuid();
        $now = now();

        DB::table('core_tasks')->insert([
            'task_id' => $taskId,
            'module_key' => 'test-module',
            'task_type' => 'test-task',
            'source_type' => 'test-source',
            'source_id' => 'source-default-status',
            'created_by_account_id' => null,
            'assigned_account_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $taskId,
            'generic_status' => CoreTask::STATUS_OPEN,
        ]);
    }

    public function test_database_rejects_duplicate_source_identity(): void
    {
        $now = now();

        $identity = [
            'module_key' => 'test-module',
            'task_type' => 'test-task',
            'source_type' => 'test-source',
            'source_id' => 'source-duplicate-001',
            'created_by_account_id' => null,
            'assigned_account_id' => null,
            'generic_status' => CoreTask::STATUS_OPEN,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('core_tasks')->insert([
            ...$identity,
            'task_id' => (string) Str::uuid(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('core_tasks')->insert([
            ...$identity,
            'task_id' => (string) Str::uuid(),
        ]);
    }

    public function test_same_source_id_is_allowed_for_different_identity_dimensions(): void
    {
        $now = now();
        $sourceId = 'shared-source-001';

        $records = [
            [
                'module_key' => 'module-alpha',
                'task_type' => 'TASK_ALPHA',
                'source_type' => 'test-source',
            ],
            [
                'module_key' => 'module-beta',
                'task_type' => 'TASK_ALPHA',
                'source_type' => 'test-source',
            ],
            [
                'module_key' => 'module-alpha',
                'task_type' => 'TASK_BETA',
                'source_type' => 'test-source',
            ],
            [
                'module_key' => 'module-alpha',
                'task_type' => 'TASK_ALPHA',
                'source_type' => 'another-source',
            ],
        ];

        foreach ($records as $record) {
            DB::table('core_tasks')->insert([
                'task_id' => (string) Str::uuid(),
                'module_key' => $record['module_key'],
                'task_type' => $record['task_type'],
                'source_type' => $record['source_type'],
                'source_id' => $sourceId,
                'created_by_account_id' => null,
                'assigned_account_id' => null,
                'generic_status' => CoreTask::STATUS_OPEN,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->assertDatabaseCount('core_tasks', 4);
    }

    public function test_model_declares_uuid_primary_key_and_generic_statuses(): void
    {
        $task = new CoreTask();

        $this->assertSame('core_tasks', $task->getTable());
        $this->assertSame('task_id', $task->getKeyName());
        $this->assertSame('string', $task->getKeyType());
        $this->assertFalse($task->getIncrementing());

        $this->assertSame('OPEN', CoreTask::STATUS_OPEN);
        $this->assertSame('ASSIGNED', CoreTask::STATUS_ASSIGNED);
        $this->assertSame('IN_PROGRESS', CoreTask::STATUS_IN_PROGRESS);
        $this->assertSame('COMPLETED', CoreTask::STATUS_COMPLETED);
        $this->assertSame('CANCELLED', CoreTask::STATUS_CANCELLED);
    }

    public function test_registered_module_task_type_can_create_core_task(): void
    {
        $task = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'source-001'
        );

        $this->assertInstanceOf(CoreTask::class, $task);
        $this->assertNotEmpty($task->task_id);
        $this->assertSame('TestModule', $task->module_key);
        $this->assertSame('TEST_TASK', $task->task_type);
        $this->assertSame('test_source', $task->source_type);
        $this->assertSame('source-001', $task->source_id);
        $this->assertSame(CoreTask::STATUS_OPEN, $task->generic_status);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $task->task_id,
            'module_key' => 'TestModule',
            'task_type' => 'TEST_TASK',
            'source_type' => 'test_source',
            'source_id' => 'source-001',
        ]);
    }

    public function test_unknown_module_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->register(
            'UnknownModule',
            'UNKNOWN_TASK',
            'test_source',
            'source-unknown-module'
        );
    }

    public function test_module_key_matching_is_case_sensitive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->register(
            'testmodule',
            'TEST_TASK',
            'test_source',
            'source-wrong-module-case'
        );
    }

    public function test_unsupported_task_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->register(
            'TestModule',
            'UNDECLARED_TASK',
            'test_source',
            'source-unsupported-type'
        );
    }

    public function test_task_type_matching_is_case_sensitive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->register(
            'TestModule',
            'test_task',
            'test_source',
            'source-wrong-task-case'
        );
    }

    public static function emptyIdentityFieldsProvider(): array
    {
        return [
            'empty module key' => [
                '',
                'TEST_TASK',
                'test_source',
                'source-empty-module',
            ],
            'empty task type' => [
                'TestModule',
                '',
                'test_source',
                'source-empty-type',
            ],
            'empty source type' => [
                'TestModule',
                'TEST_TASK',
                '',
                'source-empty-source-type',
            ],
            'empty source id' => [
                'TestModule',
                'TEST_TASK',
                'test_source',
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
        try {
            $this->registry->register(
                $moduleKey,
                $taskType,
                $sourceType,
                $sourceId
            );

            $this->fail('Empty identity fields should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('core_tasks', 0);
        }
    }

    public static function whitespaceIdentityFieldsProvider(): array
    {
        return [
            'whitespace module key' => [
                '   ',
                'TEST_TASK',
                'test_source',
                'source-whitespace-module',
            ],
            'whitespace task type' => [
                'TestModule',
                " \t ",
                'test_source',
                'source-whitespace-type',
            ],
            'whitespace source type' => [
                'TestModule',
                'TEST_TASK',
                " \t ",
                'source-whitespace-source-type',
            ],
            'whitespace source id' => [
                'TestModule',
                'TEST_TASK',
                'test_source',
                " \t ",
            ],
        ];
    }

    #[DataProvider('whitespaceIdentityFieldsProvider')]
    public function test_whitespace_only_identity_fields_are_rejected(
        string $moduleKey,
        string $taskType,
        string $sourceType,
        string $sourceId
    ): void {
        try {
            $this->registry->register(
                $moduleKey,
                $taskType,
                $sourceType,
                $sourceId
            );

            $this->fail('Whitespace-only identity fields should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('core_tasks', 0);
        }
    }

    public static function rejectedRegistrationProvider(): array
    {
        return [
            'unknown module' => [
                'UnknownModule',
                'UNKNOWN_TASK',
                'unknown_source',
                'rejected-unknown-module',
            ],
            'unsupported task type' => [
                'TestModule',
                'UNDECLARED_TASK',
                'test_source',
                'rejected-unsupported-type',
            ],
            'incorrect module key case' => [
                'testmodule',
                'TEST_TASK',
                'test_source',
                'rejected-module-case',
            ],
            'incorrect task type case' => [
                'TestModule',
                'test_task',
                'test_source',
                'rejected-task-type-case',
            ],
        ];
    }

    #[DataProvider('rejectedRegistrationProvider')]
    public function test_rejected_registration_does_not_persist_a_task(
        string $moduleKey,
        string $taskType,
        string $sourceType,
        string $sourceId
    ): void {
        try {
            $this->registry->register(
                $moduleKey,
                $taskType,
                $sourceType,
                $sourceId
            );

            $this->fail('Unsupported registration should be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('core_tasks', 0);
        }
    }

    public function test_repeated_registration_returns_existing_task(): void
    {
        $first = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-idempotent-001'
        );

        $second = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-idempotent-001'
        );

        $this->assertSame($first->task_id, $second->task_id);
        $this->assertSame('test_source', $second->source_type);
        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_different_source_types_with_same_source_id_create_separate_tasks(): void
    {
        $first = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'marketplace_request',
            'shared-source-identity-001'
        );

        $second = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'another_source',
            'shared-source-identity-001'
        );

        $this->assertNotSame($first->task_id, $second->task_id);

        $this->assertSame('marketplace_request', $first->source_type);
        $this->assertSame('another_source', $second->source_type);

        $this->assertSame($first->source_id, $second->source_id);
        $this->assertDatabaseCount('core_tasks', 2);
    }

    public function test_different_source_ids_create_separate_tasks(): void
    {
        $first = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-101'
        );

        $second = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-102'
        );

        $this->assertNotSame($first->task_id, $second->task_id);
        $this->assertDatabaseCount('core_tasks', 2);
    }

    public function test_identity_fields_are_trimmed_before_registration(): void
    {
        $task = $this->registry->register(
            ' TestModule ',
            ' TEST_TASK ',
            ' test_source ',
            ' request-trim-001 '
        );

        $this->assertSame('TestModule', $task->module_key);
        $this->assertSame('TEST_TASK', $task->task_type);
        $this->assertSame('test_source', $task->source_type);
        $this->assertSame('request-trim-001', $task->source_id);
    }

    public function test_created_by_account_id_is_stored_on_registration(): void
    {
        $accountId = '11111111-1111-4111-8111-111111111111';

        $task = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
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
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-creator-idempotent-001',
            $originalCreator
        );

        $second = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-creator-idempotent-001',
            $secondCreator
        );

        $this->assertSame($first->task_id, $second->task_id);
        $this->assertSame($originalCreator, $second->created_by_account_id);
        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_repeated_registration_preserves_null_creator(): void
    {
        $first = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-null-creator-001'
        );

        $second = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-null-creator-001',
            '11111111-1111-4111-8111-111111111111'
        );

        $this->assertSame($first->task_id, $second->task_id);
        $this->assertNull($second->created_by_account_id);
        $this->assertSame(CoreTask::STATUS_OPEN, $second->generic_status);

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $first->task_id,
            'created_by_account_id' => null,
            'generic_status' => CoreTask::STATUS_OPEN,
        ]);

        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_normalized_identity_returns_existing_task(): void
    {
        $first = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-normalization-001'
        );

        $second = $this->registry->register(
            ' TestModule ',
            ' TEST_TASK ',
            ' test_source ',
            ' request-normalization-001 '
        );

        $this->assertSame($first->task_id, $second->task_id);
        $this->assertSame('TestModule', $second->module_key);
        $this->assertSame('TEST_TASK', $second->task_type);
        $this->assertSame('test_source', $second->source_type);
        $this->assertSame('request-normalization-001', $second->source_id);
        $this->assertSame(CoreTask::STATUS_OPEN, $second->generic_status);
        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_repeated_registration_preserves_existing_lifecycle_state(): void
    {
        $first = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-lifecycle-001',
            '11111111-1111-4111-8111-111111111111'
        );

        $assignedAt = Carbon::parse('2026-10-15 10:30:00');

        $first->generic_status = CoreTask::STATUS_ASSIGNED;
        $first->assigned_account_id = '22222222-2222-4222-8222-222222222222';
        $first->assigned_at = $assignedAt;
        $first->save();

        $second = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-lifecycle-001',
            '33333333-3333-4333-8333-333333333333'
        );

        $second->refresh();

        $this->assertSame($first->task_id, $second->task_id);
        $this->assertSame(CoreTask::STATUS_ASSIGNED, $second->generic_status);

        $this->assertSame(
            '22222222-2222-4222-8222-222222222222',
            $second->assigned_account_id
        );

        $this->assertTrue($second->assigned_at->equalTo($assignedAt));
        $this->assertSame('test_source', $second->source_type);

        $this->assertSame(
            '11111111-1111-4111-8111-111111111111',
            $second->created_by_account_id
        );

        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_source_ids_differing_only_by_case_are_distinct(): void
    {
        $upperCase = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-Case-001'
        );

        $lowerCase = $this->registry->register(
            'TestModule',
            'TEST_TASK',
            'test_source',
            'request-case-001'
        );

        $this->assertNotSame($upperCase->task_id, $lowerCase->task_id);
        $this->assertSame('request-Case-001', $upperCase->source_id);
        $this->assertSame('request-case-001', $lowerCase->source_id);
        $this->assertSame(CoreTask::STATUS_OPEN, $upperCase->generic_status);
        $this->assertSame(CoreTask::STATUS_OPEN, $lowerCase->generic_status);
        $this->assertDatabaseCount('core_tasks', 2);
    }

    public function test_new_task_has_empty_lifecycle_timestamps(): void
    {
        $task = CoreTask::create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'TestModule',
            'task_type' => 'TEST_TASK',
            'source_type' => 'test_source',
            'source_id' => 'model-contract-001',
        ]);

        $task->refresh();

        $this->assertSame(CoreTask::STATUS_OPEN, $task->generic_status);
        $this->assertNull($task->assigned_account_id);
        $this->assertNull($task->assigned_at);
        $this->assertNull($task->started_at);
        $this->assertNull($task->completed_at);
        $this->assertNull($task->cancelled_at);
    }

    public function test_lifecycle_timestamps_are_cast_to_datetime(): void
    {
        $casts = (new CoreTask())->getCasts();

        $this->assertSame('datetime', $casts['assigned_at']);
        $this->assertSame('datetime', $casts['started_at']);
        $this->assertSame('datetime', $casts['completed_at']);
        $this->assertSame('datetime', $casts['cancelled_at']);
    }

    public function test_task_id_is_a_string_uuid_primary_key(): void
    {
        $taskId = (string) Str::uuid();

        $task = CoreTask::create([
            'task_id' => $taskId,
            'module_key' => 'TestModule',
            'task_type' => 'TEST_TASK',
            'source_type' => 'test_source',
            'source_id' => 'model-contract-002',
        ]);

        $this->assertSame($taskId, $task->task_id);
        $this->assertSame('task_id', $task->getKeyName());
        $this->assertFalse($task->getIncrementing());

        $this->assertDatabaseHas('core_tasks', [
            'task_id' => $taskId,
            'generic_status' => CoreTask::STATUS_OPEN,
        ]);
    }
}
