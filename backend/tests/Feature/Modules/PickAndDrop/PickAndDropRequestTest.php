<?php

namespace Tests\Feature\Modules\PickAndDrop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickAndDropRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_pick_and_drop_request_endpoint_is_not_registered(): void
    {
        $this->postJson('/api/pick-and-drop/requests', [])
            ->assertNotFound();

        $this->assertDatabaseCount('pick_and_drop_requests', 0);
        $this->assertDatabaseCount('pick_and_drop_tasks', 0);
    }

    public function test_pick_and_drop_foundation_endpoint_is_not_registered(): void
    {
        $this->getJson('/api/pick-and-drop/foundation/status')
            ->assertNotFound();
    }
}
