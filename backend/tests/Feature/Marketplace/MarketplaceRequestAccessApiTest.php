<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceRequestAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_view_another_customers_request(): void
    {
        $owner = $this->makeAccount();
        $otherCustomer = $this->makeAccount();

        $ownerToken = $this->createSessionToken($owner);
        $otherCustomerToken = $this->createSessionToken($otherCustomer);

        $created = $this->withToken($ownerToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Private request owned by another customer.',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $requestId = $created->json('data.request.request_id');

        $this->assertNotEmpty($requestId);

        $this->withToken($otherCustomerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'customer_account_id' => $owner->id,
            'status' => 'OPEN',
        ]);
    }

    private function makeAccount(): Account
    {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Request Access API Test',
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
