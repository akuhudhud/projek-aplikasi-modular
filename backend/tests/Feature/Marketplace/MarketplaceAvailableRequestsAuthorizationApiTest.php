<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceAvailableRequestsAuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_approved_runner_cannot_browse_available_requests(): void
    {
        $runner = $this->makeSuspendedApprovedRunner();
        $token = $this->createSessionToken($runner);

        $this->withToken($token)
            ->getJson('/api/marketplace/requests?view=available')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function makeSuspendedApprovedRunner(): Account
    {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Suspended Approved Runner API Test',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'SUSPENDED',
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
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
