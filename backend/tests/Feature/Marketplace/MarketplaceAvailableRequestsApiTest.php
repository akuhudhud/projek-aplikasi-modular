<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\RunnerPublicProfile;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceAvailableRequestsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_only_sees_open_unexpired_requests_available_to_them(): void
    {
        $customer = $this->makeAccount();
        $runnerOne = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );
        $runnerTwo = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $customerToken = $this->createSessionToken($customer);
        $runnerOneToken = $this->createSessionToken($runnerOne);

        $this->createPublicProfile(
            $runnerOne,
            'available-runner-one'
        );

        $this->createPublicProfile(
            $runnerTwo,
            'available-runner-two'
        );

        $generalRequest = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Open request available to all approved runners.',
            ])
            ->assertCreated();

        $generalRequestId = $generalRequest->json(
            'data.request.request_id'
        );

        $targetedRequest = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'source_type' => 'runner_profile_link',
                'target_runner_slug' => 'available-runner-one',
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Request intended for runner one only.',
            ])
            ->assertCreated();

        $targetedRequestId = $targetedRequest->json(
            'data.request.request_id'
        );

        $otherRunnerRequest = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'source_type' => 'runner_profile_link',
                'target_runner_slug' => 'available-runner-two',
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Request intended for runner two only.',
            ])
            ->assertCreated();

        $otherRunnerRequestId = $otherRunnerRequest->json(
            'data.request.request_id'
        );

        DB::table('marketplace_requests')
            ->where('request_id', $generalRequestId)
            ->update([
                'expires_at' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        $response = $this->withToken($runnerOneToken)
            ->getJson('/api/marketplace/requests?view=available')
            ->assertOk()
            ->assertJsonPath('success', true);

        $requests = collect(
            $response->json('data.requests')
        );

        $visibleRequestIds = $requests
            ->pluck('request_id')
            ->all();

        $this->assertContains(
            $targetedRequestId,
            $visibleRequestIds
        );

        $this->assertNotContains(
            $generalRequestId,
            $visibleRequestIds
        );

        $this->assertNotContains(
            $otherRunnerRequestId,
            $visibleRequestIds
        );
    }

    private function createPublicProfile(
        Account $runner,
        string $slug
    ): RunnerPublicProfile {
        return RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => $slug,
            'display_name' => 'Test Runner',
            'service_area' => 'Tuaran',
            'services' => ['General service'],
            'is_published' => true,
        ]);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Available Requests API Test',
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
