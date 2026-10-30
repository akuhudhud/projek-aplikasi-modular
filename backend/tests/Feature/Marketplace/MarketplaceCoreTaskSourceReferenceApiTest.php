<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceCoreTaskSourceReferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_task_references_marketplace_request_for_both_task_classes(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $taskClasses = [
            'PICKUP_DELIVERY',
            'NON_DELIVERY',
        ];

        foreach ($taskClasses as $index => $taskClass) {
            $payload = [
                'task_class' => $taskClass,
                'description' => 'Source reference test '.$index,
            ];

            if ($taskClass === 'PICKUP_DELIVERY') {
                $payload['pickup_location_snapshot'] = [
                    'address' => 'Pickup location '.$index,
                ];

                $payload['delivery_location_snapshot'] = [
                    'address' => 'Delivery location '.$index,
                ];
            } else {
                $payload['task_location_snapshot'] = [
                    'address' => 'Task location '.$index,
                ];
            }

            $created = $this->withToken($customerToken)
                ->postJson('/api/marketplace/requests', $payload)
                ->assertCreated()
                ->assertJsonPath('success', true);

            $requestId = $created->json(
                'data.request.request_id'
            );

            $this->assertNotEmpty($requestId);

            $quotation = $this->withToken($runnerToken)
                ->postJson(
                    '/api/marketplace/requests/'.$requestId.'/quotations',
                    ['amount' => '20.00']
                )
                ->assertCreated()
                ->assertJsonPath('success', true);

            $quotationId = $quotation->json(
                'data.quotation.quotation_id'
            );

            $this->assertNotEmpty($quotationId);

            $award = $this->withToken($customerToken)
                ->postJson(
                    '/api/marketplace/requests/'.$requestId.'/award',
                    ['quotation_id' => $quotationId]
                )
                ->assertOk()
                ->assertJsonPath('success', true);

            $taskId = $award->json('data.task.task_id');

            $this->assertNotEmpty($taskId);

            $this->assertDatabaseHas('marketplace_requests', [
                'request_id' => $requestId,
                'task_class' => $taskClass,
                'status' => 'AWARDED',
            ]);

            $this->assertDatabaseHas('core_tasks', [
                'task_id' => $taskId,
                'module_key' => 'Marketplace',
                'task_type' => 'MARKETPLACE_SERVICE_REQUEST',
                'source_type' => 'marketplace_request',
                'source_id' => $requestId,
                'assigned_account_id' => $runner->id,
            ]);

            $this->assertDatabaseMissing('core_tasks', [
                'task_id' => $taskId,
                'source_id' => 'invalid-source',
            ]);
        }

        $this->assertDatabaseCount('marketplace_requests', 2);
        $this->assertDatabaseCount('core_tasks', 2);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Source Reference Test',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => $runnerStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }

    private function createSessionToken(Account $account): string
    {
        $token = Str::random(64);

        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        return $token;
    }
}
