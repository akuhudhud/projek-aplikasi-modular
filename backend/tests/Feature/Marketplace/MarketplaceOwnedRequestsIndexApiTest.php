<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceOwnedRequestsIndexApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_request_list_only_returns_requests_owned_by_authenticated_customer(): void
    {
        $customerOne = $this->makeAccount();
        $customerTwo = $this->makeAccount();

        $customerOneToken = $this->createSessionToken($customerOne);
        $customerTwoToken = $this->createSessionToken($customerTwo);

        $firstRequest = $this->withToken($customerOneToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'First customer private request.',
            ])
            ->assertCreated();

        $firstRequestId = $firstRequest->json(
            'data.request.request_id'
        );

        $secondRequest = $this->withToken($customerTwoToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Second customer private request.',
            ])
            ->assertCreated();

        $secondRequestId = $secondRequest->json(
            'data.request.request_id'
        );

        $response = $this->withToken($customerOneToken)
            ->getJson('/api/marketplace/requests')
            ->assertOk()
            ->assertJsonPath('success', true);

        $requests = collect(
            $response->json('data.requests')
        );

        $visibleRequestIds = $requests
            ->pluck('request_id')
            ->all();

        $this->assertContains(
            $firstRequestId,
            $visibleRequestIds
        );

        $this->assertNotContains(
            $secondRequestId,
            $visibleRequestIds
        );

        foreach ($requests as $request) {
            $this->assertSame(
                $customerOne->id,
                $request['customer_account_id']
            );
        }

        $this->assertSame(
            1,
            $response->json('data.pagination.total')
        );
    }

    public function test_customer_can_explicitly_request_the_mine_view(): void
    {
        $customer = $this->makeAccount();
        $token = $this->createSessionToken($customer);

        $created = $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Explicit mine view request.',
            ])
            ->assertCreated();

        $requestId = $created->json(
            'data.request.request_id'
        );

        $this->withToken($token)
            ->getJson('/api/marketplace/requests?view=mine')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.requests.0.request_id',
                $requestId
            )
            ->assertJsonPath(
                'data.requests.0.customer_account_id',
                $customer->id
            );
    }

    private function makeAccount(): Account
    {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Owned Requests Index API Test',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => Account::RUNNER_STATUS_NOT_RUNNER,
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
