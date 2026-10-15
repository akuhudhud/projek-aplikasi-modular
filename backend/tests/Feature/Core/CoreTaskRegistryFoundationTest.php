<?php

namespace Tests\Feature\Core;

use App\Models\CoreTask;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreTaskRegistryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_tasks_table_contains_required_registry_fields(): void
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
            'source_id' => 'source-001',
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

    public function test_registry_rejects_duplicate_source_identity(): void
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

    public function test_core_task_model_declares_uuid_primary_key_and_statuses(): void
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
}
