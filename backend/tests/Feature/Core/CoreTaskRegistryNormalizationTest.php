<?php

namespace Tests\Feature\Core;

use App\Models\CoreTask;
use App\Services\CoreTaskRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreTaskRegistryNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalized_identity_returns_existing_task(): void
    {
        $registry = new CoreTaskRegistryService();

        $first = $registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-normalization-001'
        );

        $second = $registry->register(
            ' PickAndDrop ',
            ' PICK_AND_DROP_REQUEST ',
            ' different_source_label ',
            ' request-normalization-001 '
        );

        $this->assertSame($first->task_id, $second->task_id);

        $this->assertSame('PickAndDrop', $second->module_key);
        $this->assertSame(
            'PICK_AND_DROP_REQUEST',
            $second->task_type
        );
        $this->assertSame(
            'pick_and_drop_request',
            $second->source_type
        );
        $this->assertSame(
            'request-normalization-001',
            $second->source_id
        );
        $this->assertSame(
            CoreTask::STATUS_OPEN,
            $second->generic_status
        );

        $this->assertDatabaseCount('core_tasks', 1);
    }
}
