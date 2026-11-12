<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerTaskRatingWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Rating Web Test',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticateWeb(Account $account): void
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

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    private function createCompletedTask(
        Account $customer,
        Account $runner
    ): CoreTask {
        $requestId = (string) Str::uuid();

        MarketplaceRequest::query()->create([
            'request_id' => $requestId,
            'customer_account_id' => $customer->id,
            'target_runner_account_id' => $runner->id,
            'source_type' => MarketplaceRequest::SOURCE_MARKETPLACE,
            'task_class' => MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            'pickup_location_snapshot' => [
                'address' => 'Pickup location',
            ],
            'delivery_location_snapshot' => [
                'address' => 'Delivery location',
            ],
            'description' => 'Rating web test request.',
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'awarded_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        return CoreTask::query()->create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'Marketplace',
            'task_type' => 'MARKETPLACE_SERVICE_REQUEST',
            'source_type' => 'marketplace_request',
            'source_id' => $requestId,
            'created_by_account_id' => $customer->id,
            'assigned_account_id' => $runner->id,
            'generic_status' => CoreTask::STATUS_COMPLETED,
            'assigned_at' => now(),
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function test_customer_can_submit_rating_through_web_endpoint(): void
    {
        $customer = $this->createAccount();
        $runner = $this->createAccount();

        $task = $this->createCompletedTask(
            $customer,
            $runner
        );

        $this->authenticateWeb($customer);

        $this->postJson(
            '/web/customer/tasks/'.$task->task_id.'/rating',
            [
                'rating' => 5,
                'review' => 'Runner sangat membantu.',
            ]
        )
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.rating.rating',
                5
            )
            ->assertJsonPath(
                'data.rating.review',
                'Runner sangat membantu.'
            )
            ->assertJsonMissingPath(
                'data.rating.customer_account_id'
            )
            ->assertJsonMissingPath(
                'data.rating.runner_account_id'
            );

        $this->assertDatabaseHas('runner_task_ratings', [
            'task_id' => $task->task_id,
            'customer_account_id' => $customer->id,
            'runner_account_id' => $runner->id,
            'rating' => 5,
            'review' => 'Runner sangat membantu.',
        ]);

        $this->assertDatabaseCount('runner_task_ratings', 1);
    }

    public function test_customer_can_update_rating_through_web_endpoint(): void
    {
        $customer = $this->createAccount();
        $runner = $this->createAccount();

        $task = $this->createCompletedTask(
            $customer,
            $runner
        );

        $this->authenticateWeb($customer);

        $this->postJson(
            '/web/customer/tasks/'.$task->task_id.'/rating',
            [
                'rating' => 3,
                'review' => 'Pengalaman sederhana.',
            ]
        )->assertCreated();

        $this->patchJson(
            '/web/customer/tasks/'.$task->task_id.'/rating',
            [
                'rating' => 5,
                'review' => 'Pengalaman sangat baik.',
            ]
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.rating.rating',
                5
            )
            ->assertJsonPath(
                'data.rating.review',
                'Pengalaman sangat baik.'
            );

        $this->assertDatabaseCount('runner_task_ratings', 1);

        $this->assertDatabaseHas('runner_task_ratings', [
            'task_id' => $task->task_id,
            'customer_account_id' => $customer->id,
            'runner_account_id' => $runner->id,
            'rating' => 5,
            'review' => 'Pengalaman sangat baik.',
        ]);
    }

    public function test_web_rating_endpoints_require_customer_session(): void
    {
        $taskId = (string) Str::uuid();

        $this->postJson(
            '/web/customer/tasks/'.$taskId.'/rating',
            [
                'rating' => 5,
            ]
        )->assertUnauthorized();

        $this->patchJson(
            '/web/customer/tasks/'.$taskId.'/rating',
            [
                'rating' => 5,
            ]
        )->assertUnauthorized();
    }
}
