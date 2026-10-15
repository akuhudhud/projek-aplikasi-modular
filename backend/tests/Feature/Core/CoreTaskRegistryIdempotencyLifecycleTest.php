<?php

namespace Tests\Feature\Core;

use App\Models\CoreTask;
use App\Services\CoreTaskRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CoreTaskRegistryIdempotencyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_registration_preserves_existing_lifecycle_state(): void
    {
        $registry = new CoreTaskRegistryService();

        $first = $registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-lifecycle-001',
            '11111111-1111-4111-8111-111111111111'
        );

        $assignedAt = Carbon::parse('2026-10-15 10:30:00');

        $first->generic_status = CoreTask::STATUS_ASSIGNED;
        $first->assigned_account_id =
            '22222222-2222-4222-8222-222222222222';
        $first->assigned_at = $assignedAt;
        $first->save();

        $second = $registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'different_source_label',
            'request-lifecycle-001',
            '33333333-3333-4333-8333-333333333333'
        );

        $second->refresh();

        $this->assertSame($first->task_id, $second->task_id);
        $this->assertSame(
            CoreTask::STATUS_ASSIGNED,
            $second->generic_status
        );
        $this->assertSame(
            '22222222-2222-4222-8222-222222222222',
            $second->assigned_account_id
        );
        $this->assertTrue(
            $second->assigned_at->equalTo($assignedAt)
        );
        $this->assertSame(
            'pick_and_drop_request',
            $second->source_type
        );
        $this->assertSame(
            '11111111-1111-4111-8111-111111111111',
            $second->created_by_account_id
        );

        $this->assertDatabaseCount('core_tasks', 1);
    }
}
