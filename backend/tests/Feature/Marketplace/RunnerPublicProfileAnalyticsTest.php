<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Models\RunnerPublicProfile;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerPublicProfileAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_sees_only_analytics_for_requests_from_own_profile(): void
    {
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);
        $otherRunner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $this->createSessionToken($runner);

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => 'analytics-runner',
            'display_name' => 'Analytics Runner',
            'is_published' => true,
            'views_count' => 17,
        ]);

        $profileRequest = $this->makeRequest(
            $runner,
            MarketplaceRequest::SOURCE_RUNNER_PROFILE_LINK
        );

        $marketplaceRequest = $this->makeRequest(
            $runner,
            MarketplaceRequest::SOURCE_MARKETPLACE
        );

        $otherRunnerProfileRequest = $this->makeRequest(
            $otherRunner,
            MarketplaceRequest::SOURCE_RUNNER_PROFILE_LINK
        );

        $this->makeQuotation($profileRequest);
        $this->makeQuotation($profileRequest);
        $this->makeQuotation($marketplaceRequest);
        $this->makeQuotation($otherRunnerProfileRequest);

        $this->makeCompletedTask($runner, $profileRequest);
        $this->makeCompletedTask($runner, $marketplaceRequest);
        $this->makeCompletedTask($otherRunner, $otherRunnerProfileRequest);

        $token = $this->createSessionToken($runner);

        $this->withToken($token)
            ->getJson('/api/me/runner-profile/analytics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.analytics.profile_views', 17)
            ->assertJsonPath('data.analytics.requests_from_profile', 1)
            ->assertJsonPath(
                'data.analytics.quotations_for_profile_requests',
                2
            )
            ->assertJsonPath(
                'data.analytics.completed_tasks_from_profile_requests',
                1
            )
            ->assertJsonMissingPath('data.analytics.customer_account_id')
            ->assertJsonMissingPath('data.analytics.customer_phone');
    }

    public function test_runner_without_a_profile_receives_not_found(): void
    {
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);
        $token = $this->createSessionToken($runner);

        $this->withToken($token)
            ->getJson('/api/me/runner-profile/analytics')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_unapproved_runner_cannot_view_profile_analytics(): void
    {
        $runner = $this->makeAccount(Account::RUNNER_STATUS_PENDING);
        $token = $this->createSessionToken($runner);

        $this->withToken($token)
            ->getJson('/api/me/runner-profile/analytics')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Analytics Test Account',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => $runnerStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }

    private function makeRequest(
        Account $runner,
        string $sourceType
    ): MarketplaceRequest {
        return MarketplaceRequest::query()->create([
            'request_id' => (string) Str::uuid(),
            'customer_account_id' => (string) Str::uuid(),
            'target_runner_account_id' =>
                $sourceType === MarketplaceRequest::SOURCE_RUNNER_PROFILE_LINK
                    ? $runner->id
                    : null,
            'source_type' => $sourceType,
            'task_class' => MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            'pickup_location_snapshot' => [
                'address' => 'Pickup location',
            ],
            'delivery_location_snapshot' => [
                'address' => 'Delivery location',
            ],
            'description' => 'Analytics test request',
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);
    }

    private function makeQuotation(
        MarketplaceRequest $request
    ): MarketplaceQuotation {
        return MarketplaceQuotation::query()->create([
            'quotation_id' => (string) Str::uuid(),
            'request_id' => $request->request_id,
            'runner_account_id' => (string) Str::uuid(),
            'amount' => '25.00',
            'currency' => 'MYR',
            'message' => 'Analytics test quotation',
            'status' => MarketplaceQuotation::STATUS_SUBMITTED,
        ]);
    }

    private function makeCompletedTask(
        Account $runner,
        MarketplaceRequest $request
    ): CoreTask {
        return CoreTask::query()->create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'Marketplace',
            'task_type' => 'MARKETPLACE_SERVICE_REQUEST',
            'source_type' => 'marketplace_request',
            'source_id' => $request->request_id,
            'created_by_account_id' => $request->customer_account_id,
            'assigned_account_id' => $runner->id,
            'generic_status' => CoreTask::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
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
