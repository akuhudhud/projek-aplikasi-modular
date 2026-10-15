<?php

namespace Tests\Feature\Core;

use App\Models\CoreTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreTaskModelContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_task_has_empty_lifecycle_timestamps(): void
    {
        $task = CoreTask::create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'PickAndDrop',
            'task_type' => 'PICK_AND_DROP_REQUEST',
            'source_type' => 'pick_and_drop_request',
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
        $task = new CoreTask();

        $casts = $task->getCasts();

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
            'module_key' => 'PickAndDrop',
            'task_type' => 'PICK_AND_DROP_REQUEST',
            'source_type' => 'pick_and_drop_request',
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
