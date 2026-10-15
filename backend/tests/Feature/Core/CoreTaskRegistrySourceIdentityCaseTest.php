<?php

namespace Tests\Feature\Core;

use App\Models\CoreTask;
use App\Services\CoreTaskRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreTaskRegistrySourceIdentityCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_ids_differing_only_by_case_are_distinct(): void
    {
        $registry = new CoreTaskRegistryService();

        $upperCase = $registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-Case-001'
        );

        $lowerCase = $registry->register(
            'PickAndDrop',
            'PICK_AND_DROP_REQUEST',
            'pick_and_drop_request',
            'request-case-001'
        );

        $this->assertNotSame(
            $upperCase->task_id,
            $lowerCase->task_id
        );

        $this->assertSame(
            'request-Case-001',
            $upperCase->source_id
        );

        $this->assertSame(
            'request-case-001',
            $lowerCase->source_id
        );

        $this->assertSame(
            CoreTask::STATUS_OPEN,
            $upperCase->generic_status
        );

        $this->assertSame(
            CoreTask::STATUS_OPEN,
            $lowerCase->generic_status
        );

        $this->assertDatabaseCount('core_tasks', 2);
    }
}
