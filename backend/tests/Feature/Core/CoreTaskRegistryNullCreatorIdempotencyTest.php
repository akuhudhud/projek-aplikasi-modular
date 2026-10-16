<?php

namespace Tests\Feature\Core;

use App\Models\CoreTask;
use App\Services\CoreTaskRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreTaskRegistryNullCreatorIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_registration_does_not_replace_null_creator(): void
    {
        $registry = new CoreTaskRegistryService();

        $first = $registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-null-creator-001'
        );

        $second = $registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
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
}
