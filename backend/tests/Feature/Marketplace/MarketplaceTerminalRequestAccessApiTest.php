<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceTerminalRequestAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_cancelled_request_but_runner_cannot(): void
    {
        $owner = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $ownerToken = $this->createSessionToken($owner);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($ownerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Cancelled request access regression.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $this->withToken($ownerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/cancel'
            )
            ->assertOk()
            ->assertJsonPath('data.request.status', 'CANCELLED');

        $this->withToken($ownerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', 'CANCELLED');

        $this->withToken($runnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'customer_account_id' => $owner->id,
            'status' => MarketplaceRequest::STATUS_CANCELLED,
        ]);
    }

    public function test_owner_can_view_expired_request_but_runner_cannot(): void
    {
        $owner = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $ownerToken = $this->createSessionToken($owner);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($ownerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Expired request access regression.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        DB::table('marketplace_requests')
            ->where('request_id', $requestId)
            ->update([
                'expires_at' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // The middleware expires due requests before the controller runs.
        $this->withToken($ownerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', 'EXPIRED');

        $this->withToken($runnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'customer_account_id' => $owner->id,
            'status' => MarketplaceRequest::STATUS_EXPIRED,
        ]);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Terminal Access Test',
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
