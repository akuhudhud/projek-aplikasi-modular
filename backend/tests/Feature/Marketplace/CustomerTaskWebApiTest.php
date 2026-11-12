<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerTaskWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Customer Task Test',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    private function createWebSession(Account $account): string
    {
        $token = Str::random(64);

        Session::create([
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

    private function authenticateCustomer(Account $account): void
    {
        $token = $this->createWebSession($account);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    private function createMarketplaceTask(
        Account $customer,
        string $taskClass,
        ?Account $runner = null,
        string $status = CoreTask::STATUS_ASSIGNED
    ): array {
        $requestId = (string) Str::uuid();

        $requestData = [
            'request_id' => $requestId,
            'customer_account_id' => $customer->id,
            'task_class' => $taskClass,
            'description' => 'Customer task test '.$taskClass,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'expires_at' => now()->addHours(2),
            'awarded_at' => now(),
        ];

        if ($taskClass === MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY) {
            $requestData['pickup_location_snapshot'] = [
                'address' => 'Pickup address',
            ];

            $requestData['delivery_location_snapshot'] = [
                'address' => 'Delivery address',
            ];
        } else {
            $requestData['task_location_snapshot'] = [
                'address' => 'Task location',
            ];
        }

        MarketplaceRequest::create($requestData);

        $task = CoreTask::create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'Marketplace',
            'task_type' => 'MARKETPLACE_SERVICE_REQUEST',
            'source_type' => 'marketplace_request',
            'source_id' => $requestId,
            'created_by_account_id' => $customer->id,
            'assigned_account_id' => $runner?->id,
            'generic_status' => $status,
            'assigned_at' => now(),
            'completed_at' => $status === CoreTask::STATUS_COMPLETED
                ? now()
                : null,
        ]);

        return [
            'request_id' => $requestId,
            'task' => $task,
        ];
    }

    public function test_customer_can_only_view_own_marketplace_tasks(): void
    {
        $customer = $this->createAccount();
        $otherCustomer = $this->createAccount();
        $runner = $this->createAccount();

        $ownTask = $this->createMarketplaceTask(
            $customer,
            MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            $runner
        );

        $otherTask = $this->createMarketplaceTask(
            $otherCustomer,
            MarketplaceRequest::TASK_CLASS_NON_DELIVERY
        );

        $this->authenticateCustomer($customer);

        $response = $this->getJson('/web/customer/tasks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath(
                'data.tasks.0.task.task_id',
                $ownTask['task']->task_id
            )
            ->assertJsonPath(
                'data.tasks.0.request.request_id',
                $ownTask['request_id']
            )
            ->assertJsonMissing([
                'task_id' => $otherTask['task']->task_id,
            ])
            ->assertJsonMissingPath(
                'data.tasks.0.task.assigned_account_id'
            )
            ->assertJsonMissingPath(
                'data.tasks.0.task.created_by_account_id'
            )
            ->assertJsonMissingPath(
                'data.tasks.0.request.customer_account_id'
            )
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.pagination.per_page', 25);
    }

    public function test_customer_active_task_summary_counts_only_assigned_and_in_progress_own_tasks(): void
    {
        $customer = $this->createAccount();
        $otherCustomer = $this->createAccount();

        $this->createMarketplaceTask(
            $customer,
            MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            null,
            CoreTask::STATUS_ASSIGNED
        );

        $this->createMarketplaceTask(
            $customer,
            MarketplaceRequest::TASK_CLASS_NON_DELIVERY,
            null,
            CoreTask::STATUS_IN_PROGRESS
        );

        $this->createMarketplaceTask(
            $customer,
            MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            null,
            CoreTask::STATUS_COMPLETED
        );

        $this->createMarketplaceTask(
            $otherCustomer,
            MarketplaceRequest::TASK_CLASS_NON_DELIVERY,
            null,
            CoreTask::STATUS_ASSIGNED
        );

        $this->authenticateCustomer($customer);

        $this->getJson('/web/customer/tasks')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.active_total', 2)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_customer_can_view_own_task_details(): void
    {
        $customer = $this->createAccount();
        $runner = $this->createAccount();

        $created = $this->createMarketplaceTask(
            $customer,
            MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            $runner
        );

        $this->authenticateCustomer($customer);

        $this->getJson(
            '/web/customer/tasks/'.$created['task']->task_id
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.task.task_id',
                $created['task']->task_id
            )
            ->assertJsonPath(
                'data.request.task_class',
                MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY
            )
            ->assertJsonPath(
                'data.request.pickup_location_snapshot.address',
                'Pickup address'
            )
            ->assertJsonPath(
                'data.request.delivery_location_snapshot.address',
                'Delivery address'
            )
            ->assertJsonPath('data.rating', null)
            ->assertJsonMissingPath(
                'data.task.assigned_account_id'
            )
            ->assertJsonMissingPath(
                'data.task.created_by_account_id'
            )
            ->assertJsonMissingPath(
                'data.request.customer_account_id'
            )
            ->assertJsonMissingPath('data.task.task_class');
    }

    public function test_customer_can_view_own_saved_task_rating_without_identity_fields(): void
    {
        $customer = $this->createAccount();
        $runner = $this->createAccount();

        $created = $this->createMarketplaceTask(
            $customer,
            MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            $runner,
            CoreTask::STATUS_COMPLETED
        );

        // Match database timestamp precision (seconds).
        $createdAt = now()->subMinutes(5)->startOfSecond();
        $updatedAt = now()->startOfSecond();

        DB::table('runner_task_ratings')->insert([
            'rating_id' => (string) Str::uuid(),
            'task_id' => $created['task']->task_id,
            'customer_account_id' => $customer->id,
            'runner_account_id' => $runner->id,
            'rating' => 5,
            'review' => 'Runner sangat membantu.',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        $this->authenticateCustomer($customer);

        $this->getJson(
            '/web/customer/tasks/'.$created['task']->task_id
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating.rating', 5)
            ->assertJsonPath(
                'data.rating.review',
                'Runner sangat membantu.'
            )
            ->assertJsonPath(
                'data.rating.created_at',
                $createdAt->toJSON()
            )
            ->assertJsonPath(
                'data.rating.updated_at',
                $updatedAt->toJSON()
            )
            ->assertJsonMissingPath(
                'data.rating.rating_id'
            )
            ->assertJsonMissingPath(
                'data.rating.customer_account_id'
            )
            ->assertJsonMissingPath(
                'data.rating.runner_account_id'
            );
    }

    public function test_customer_cannot_view_another_customers_task(): void
    {
        $customer = $this->createAccount();
        $otherCustomer = $this->createAccount();

        $otherTask = $this->createMarketplaceTask(
            $otherCustomer,
            MarketplaceRequest::TASK_CLASS_NON_DELIVERY
        );

        $this->authenticateCustomer($customer);

        $this->getJson(
            '/web/customer/tasks/'.$otherTask['task']->task_id
        )
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Task not found.',
            ]);
    }

    public function test_customer_task_endpoints_require_web_session(): void
    {
        $this->getJson('/web/customer/tasks')
            ->assertUnauthorized();

        $this->getJson(
            '/web/customer/tasks/'.(string) Str::uuid()
        )
            ->assertUnauthorized();
    }

    public function test_customer_can_view_non_delivery_task_snapshot(): void
    {
        $customer = $this->createAccount();
        $runner = $this->createAccount();

        $created = $this->createMarketplaceTask(
            $customer,
            MarketplaceRequest::TASK_CLASS_NON_DELIVERY,
            $runner
        );

        $this->authenticateCustomer($customer);

        $this->getJson(
            '/web/customer/tasks/'.$created['task']->task_id
        )
            ->assertOk()
            ->assertJsonPath(
                'data.request.task_class',
                MarketplaceRequest::TASK_CLASS_NON_DELIVERY
            )
            ->assertJsonPath(
                'data.request.task_location_snapshot.address',
                'Task location'
            )
            ->assertJsonPath(
                'data.request.pickup_location_snapshot',
                null
            )
            ->assertJsonPath(
                'data.request.delivery_location_snapshot',
                null
            )
            ->assertJsonMissingPath(
                'data.task.assigned_account_id'
            )
            ->assertJsonMissingPath(
                'data.task.created_by_account_id'
            )
            ->assertJsonMissingPath(
                'data.request.customer_account_id'
            )
            ->assertJsonMissingPath('data.task.task_class');
    }
}
