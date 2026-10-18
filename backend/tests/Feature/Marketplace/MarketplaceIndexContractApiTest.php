<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\RunnerPublicProfile;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceIndexContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mine_index_returns_complete_pagination_metadata(): void
    {
        $customer = $this->makeAccount();
        $otherCustomer = $this->makeAccount();

        $token = $this->createSessionToken($customer);
        $otherToken = $this->createSessionToken($otherCustomer);

        $ownRequest = $this->createRequest(
            $token,
            'Owned request for pagination metadata.'
        );

        $this->createRequest(
            $otherToken,
            'Another customer private request.'
        );

        $response = $this->withToken($token)
            ->getJson('/api/marketplace/requests?view=mine')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 25)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.pagination.last_page', 1)
            ->assertJsonPath(
                'data.requests.0.request_id',
                $ownRequest
            );

        $this->assertCount(
            1,
            $response->json('data.requests')
        );

        $this->assertSame(
            $customer->id,
            $response->json('data.requests.0.customer_account_id')
        );
    }

    public function test_index_limits_each_page_to_25_records_without_repeating_records(): void
    {
        $customer = $this->makeAccount();
        $token = $this->createSessionToken($customer);

        $requestIds = [];

        for ($i = 1; $i <= 26; $i++) {
            $requestIds[] = $this->createRequest(
                $token,
                sprintf('Pagination contract request %02d.', $i)
            );
        }

        // Set deterministic timestamps to verify descending ordering.
        foreach ($requestIds as $index => $requestId) {
            DB::table('marketplace_requests')
                ->where('request_id', $requestId)
                ->update([
                    'created_at' => now()->subMinutes($index),
                ]);
        }

        $firstPage = $this->withToken($token)
            ->getJson('/api/marketplace/requests?view=mine&page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 25)
            ->assertJsonPath('data.pagination.total', 26)
            ->assertJsonPath('data.pagination.last_page', 2);

        $secondPage = $this->withToken($token)
            ->getJson('/api/marketplace/requests?view=mine&page=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 25)
            ->assertJsonPath('data.pagination.total', 26)
            ->assertJsonPath('data.pagination.last_page', 2);

        $firstPageIds = collect(
            $firstPage->json('data.requests')
        )->pluck('request_id')->all();

        $secondPageIds = collect(
            $secondPage->json('data.requests')
        )->pluck('request_id')->all();

        $this->assertCount(25, $firstPageIds);
        $this->assertCount(1, $secondPageIds);

        $this->assertCount(
            25,
            array_unique($firstPageIds)
        );

        $this->assertCount(
            1,
            array_unique($secondPageIds)
        );

        $this->assertSame(
            [],
            array_values(array_intersect(
                $firstPageIds,
                $secondPageIds
            ))
        );

        $this->assertSame(
            $requestIds[0],
            $firstPageIds[0]
        );

        $this->assertSame(
            $requestIds[25],
            $secondPageIds[0]
        );
    }

    public function test_available_index_includes_general_open_requests_and_excludes_expired_or_other_runner_requests(): void
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
            'index-contract-runner-one'
        );

        $this->createPublicProfile(
            $runnerTwo,
            'index-contract-runner-two'
        );

        $generalRequestId = $this->createRequest(
            $customerToken,
            'General open request available to approved runners.'
        );

        $expiredRequestId = $this->createRequest(
            $customerToken,
            'Expired request must not appear.'
        );

        $targetedRequest = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'source_type' => 'runner_profile_link',
                'target_runner_slug' => 'index-contract-runner-two',
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Request intended for another runner.',
            ])
            ->assertCreated();

        $targetedRequestId = $targetedRequest->json(
            'data.request.request_id'
        );

        $cancelledRequestId = $this->createRequest(
            $customerToken,
            'Cancelled request must not appear.'
        );

        DB::table('marketplace_requests')
            ->where('request_id', $expiredRequestId)
            ->update([
                'expires_at' => now()->subSecond(),
                'updated_at' => now(),
            ]);

        DB::table('marketplace_requests')
            ->where('request_id', $cancelledRequestId)
            ->update([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

        $response = $this->withToken($runnerOneToken)
            ->getJson('/api/marketplace/requests?view=available')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1);

        $visibleRequestIds = collect(
            $response->json('data.requests')
        )->pluck('request_id')->all();

        $this->assertSame(
            [$generalRequestId],
            $visibleRequestIds
        );

        $this->assertNotContains(
            $expiredRequestId,
            $visibleRequestIds
        );

        $this->assertNotContains(
            $targetedRequestId,
            $visibleRequestIds
        );

        $this->assertNotContains(
            $cancelledRequestId,
            $visibleRequestIds
        );
    }

    private function createRequest(
        string $token,
        string $description
    ): string {
        $response = $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => $description,
            ])
            ->assertCreated()
            ->assertJsonPath('data.request.status', 'OPEN');

        return $response->json('data.request.request_id');
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
            'name' => 'Marketplace Index Contract API Test',
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
