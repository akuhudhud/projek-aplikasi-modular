<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceCoreTaskResolutionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_resolves_marketplace_task_class_from_core_task_source_reference(): void
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
                'description' => 'Core task resolution test '.$index,
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
                    [
                        'amount' => '20.00',
                    ]
                )
                ->assertCreated()
                ->assertJsonPath('success', true);

            $quotationId = $quotation->json(
                'data.quotation.quotation_id'
            );

            $this->assertNotEmpty($quotationId);

            $this->withToken($customerToken)
                ->postJson(
                    '/api/marketplace/requests/'.$requestId.'/award',
                    [
                        'quotation_id' => $quotationId,
                    ]
                )
                ->assertOk()
                ->assertJsonPath('success', true);

            $coreTasksResponse = $this->withToken($runnerToken)
                ->getJson('/api/core/tasks')
                ->assertOk()
                ->assertJsonPath('success', true);

            $coreTasks = $coreTasksResponse->json(
                'data.tasks'
            );

            $coreTask = collect($coreTasks)->first(
                fn (array $task): bool =>
                    $task['source_type'] === 'marketplace_request'
                    && $task['source_id'] === $requestId
            );

            $this->assertNotNull(
                $coreTask,
                'Runner must be able to find the assigned Core Task.'
            );

            $this->assertSame(
                $runner->id,
                $coreTask['assigned_account_id']
            );

            $this->assertArrayNotHasKey(
                'task_class',
                $coreTask,
                'Core Task must not own Marketplace task_class.'
            );

            $resolvedRequest = $this->withToken($runnerToken)
                ->getJson(
                    '/api/marketplace/requests/'.$coreTask['source_id']
                )
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath(
                    'data.request.task_class',
                    $taskClass
                );

            $this->assertSame(
                $requestId,
                $resolvedRequest->json(
                    'data.request.request_id'
                )
            );
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
            'name' => 'Marketplace Resolution Test',
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
