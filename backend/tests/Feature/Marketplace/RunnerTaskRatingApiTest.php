<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerTaskRatingApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Rating Test Account',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        return $account->fresh();
    }

    private function authenticate(
        Account $account,
        string $token
    ): void {
        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function createCompletedMarketplaceTask(
        Account $customer,
        Account $runner,
        string $status = CoreTask::STATUS_COMPLETED
    ): CoreTask {
        $requestId = (string) Str::uuid();

        MarketplaceRequest::query()->create([
            'request_id' => $requestId,
            'customer_account_id' => $customer->id,
            'target_runner_account_id' => $runner->id,
            'source_type' => MarketplaceRequest::SOURCE_MARKETPLACE,
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Rating API test request.',
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'awarded_at' => now(),
        ]);

        return CoreTask::query()->create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'Marketplace',
            'task_type' => 'MARKETPLACE_SERVICE_REQUEST',
            'source_type' => 'marketplace_request',
            'source_id' => $requestId,
            'created_by_account_id' => $customer->id,
            'assigned_account_id' => $runner->id,
            'generic_status' => $status,
            'assigned_at' => now(),
            'started_at' => now(),
            'completed_at' => $status === CoreTask::STATUS_COMPLETED
                ? now()
                : null,
        ]);
    }

    public function test_customer_can_submit_and_edit_one_rating_for_completed_task(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $task = $this->createCompletedMarketplaceTask(
            $customer,
            $runner
        );

        $token = 'rating-customer-token';
        $this->authenticate($customer, $token);

        $first = $this->withToken($token)
            ->postJson(
                '/api/core/tasks/'.$task->task_id.'/rating',
                [
                    'rating' => 4,
                    'review' => 'Penghantaran baik.',
                    'customer_account_id' => (string) Str::uuid(),
                    'runner_account_id' => (string) Str::uuid(),
                ]
            )
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating.rating', 4)
            ->assertJsonPath(
                'data.rating.review',
                'Penghantaran baik.'
            )
            ->assertJsonMissingPath(
                'data.rating.customer_account_id'
            )
            ->assertJsonMissingPath(
                'data.rating.runner_account_id'
            );

        $ratingId = $first->json('data.rating.rating_id');

        $this->withToken($token)
            ->patchJson(
                '/api/core/tasks/'.$task->task_id.'/rating',
                ['rating' => 5]
            )
            ->assertOk()
            ->assertJsonPath('data.rating.rating_id', $ratingId)
            ->assertJsonPath('data.rating.rating', 5)
            ->assertJsonPath(
                'data.rating.review',
                'Penghantaran baik.'
            );

        $this->assertDatabaseCount('runner_task_ratings', 1);

        $this->assertDatabaseHas('runner_task_ratings', [
            'rating_id' => $ratingId,
            'task_id' => $task->task_id,
            'customer_account_id' => $customer->id,
            'runner_account_id' => $runner->id,
            'rating' => 5,
            'review' => 'Penghantaran baik.',
        ]);
    }

    public function test_zero_is_a_valid_rating(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $task = $this->createCompletedMarketplaceTask(
            $customer,
            $runner
        );

        $token = 'zero-rating-token';
        $this->authenticate($customer, $token);

        $this->withToken($token)
            ->postJson(
                '/api/core/tasks/'.$task->task_id.'/rating',
                ['rating' => 0]
            )
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating.rating', 0);

        $this->assertDatabaseHas('runner_task_ratings', [
            'task_id' => $task->task_id,
            'rating' => 0,
        ]);

        $this->assertDatabaseCount('runner_task_ratings', 1);
    }

    public function test_patch_without_existing_rating_does_not_create_rating(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $task = $this->createCompletedMarketplaceTask(
            $customer,
            $runner
        );

        $token = 'patch-without-rating-token';
        $this->authenticate($customer, $token);

        $this->withToken($token)
            ->patchJson(
                '/api/core/tasks/'.$task->task_id.'/rating',
                [
                    'rating' => 4,
                    'review' => 'Tidak sepatutnya dicipta.',
                ]
            )
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('runner_task_ratings', 0);
    }

    public function test_another_customer_cannot_rate_or_edit_the_task(): void
    {
        $customer = $this->makeAccount();
        $otherCustomer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $task = $this->createCompletedMarketplaceTask(
            $customer,
            $runner
        );

        $ownerToken = 'rating-owner-token';
        $this->authenticate($customer, $ownerToken);

        $originalResponse = $this->withToken($ownerToken)
            ->postJson(
                '/api/core/tasks/'.$task->task_id.'/rating',
                [
                    'rating' => 4,
                    'review' => 'Ulasan asal pelanggan.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.rating.rating', 4)
            ->assertJsonPath(
                'data.rating.review',
                'Ulasan asal pelanggan.'
            );

        $ratingId = $originalResponse->json(
            'data.rating.rating_id'
        );

        $otherToken = 'other-customer-token';
        $this->authenticate($otherCustomer, $otherToken);

        $this->withToken($otherToken)
            ->postJson(
                '/api/core/tasks/'.$task->task_id.'/rating',
                [
                    'rating' => 1,
                    'review' => 'Cubaan rating tanpa kebenaran.',
                ]
            )
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->withToken($otherToken)
            ->patchJson(
                '/api/core/tasks/'.$task->task_id.'/rating',
                [
                    'rating' => 1,
                    'review' => 'Cubaan mengubah rating tanpa kebenaran.',
                ]
            )
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('runner_task_ratings', 1);

        $this->assertDatabaseHas('runner_task_ratings', [
            'rating_id' => $ratingId,
            'task_id' => $task->task_id,
            'customer_account_id' => $customer->id,
            'runner_account_id' => $runner->id,
            'rating' => 4,
            'review' => 'Ulasan asal pelanggan.',
        ]);
    }

    public function test_runner_cannot_rate_their_own_task(): void
    {
        $account = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $task = $this->createCompletedMarketplaceTask(
            $account,
            $account
        );

        $token = 'self-rating-token';
        $this->authenticate($account, $token);

        $this->withToken($token)
            ->postJson(
                '/api/core/tasks/'.$task->task_id.'/rating',
                [
                    'rating' => 5,
                    'review' => 'Rating sendiri tidak dibenarkan.',
                ]
            )
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('runner_task_ratings', 0);
    }

    public function test_rating_cannot_be_deleted(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $task = $this->createCompletedMarketplaceTask(
            $customer,
            $runner
        );

        $token = 'rating-delete-test-token';
        $this->authenticate($customer, $token);

        $response = $this->withToken($token)
            ->postJson(
                '/api/core/tasks/'.$task->task_id.'/rating',
                [
                    'rating' => 5,
                    'review' => 'Ujian rating kekal.',
                ]
            )
            ->assertCreated();

        $ratingId = $response->json('data.rating.rating_id');

        $this->withToken($token)
            ->deleteJson(
                '/api/core/tasks/'.$task->task_id.'/rating'
            )
            ->assertStatus(405);

        $this->assertDatabaseCount('runner_task_ratings', 1);

        $this->assertDatabaseHas('runner_task_ratings', [
            'rating_id' => $ratingId,
            'task_id' => $task->task_id,
            'rating' => 5,
            'review' => 'Ujian rating kekal.',
        ]);
    }

    public function test_task_must_be_completed_before_rating(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $token = 'ineligible-task-customer-token';
        $this->authenticate($customer, $token);

        $ineligibleStatuses = [
            CoreTask::STATUS_IN_PROGRESS,
            CoreTask::STATUS_CANCELLED,
        ];

        foreach ($ineligibleStatuses as $status) {
            $task = $this->createCompletedMarketplaceTask(
                $customer,
                $runner,
                $status
            );

            $this->withToken($token)
                ->postJson(
                    '/api/core/tasks/'.$task->task_id.'/rating',
                    ['rating' => 5]
                )
                ->assertUnprocessable()
                ->assertJsonPath('success', false);
        }

        $this->assertDatabaseCount('runner_task_ratings', 0);
    }

    public function test_rating_must_be_an_integer_between_zero_and_five(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $task = $this->createCompletedMarketplaceTask(
            $customer,
            $runner
        );

        $token = 'invalid-rating-token';
        $this->authenticate($customer, $token);

        foreach ([-1, 6, '4.5', 'invalid'] as $value) {
            $this->withToken($token)
                ->postJson(
                    '/api/core/tasks/'.$task->task_id.'/rating',
                    ['rating' => $value]
                )
                ->assertUnprocessable();
        }

        $this->assertDatabaseCount('runner_task_ratings', 0);
    }

    public function test_rating_endpoint_requires_authentication(): void
    {
        $this->postJson(
            '/api/core/tasks/'.Str::uuid().'/rating',
            ['rating' => 5]
        )->assertUnauthorized();
    }

    public function test_rating_cannot_be_submitted_for_an_unknown_task(): void
    {
        $customer = $this->makeAccount();
        $token = 'unknown-task-token';
        $this->authenticate($customer, $token);

        $this->withToken($token)
            ->postJson(
                '/api/core/tasks/'.Str::uuid().'/rating',
                ['rating' => 5]
            )
            ->assertNotFound();

        $this->assertDatabaseCount('runner_task_ratings', 0);
    }
}
