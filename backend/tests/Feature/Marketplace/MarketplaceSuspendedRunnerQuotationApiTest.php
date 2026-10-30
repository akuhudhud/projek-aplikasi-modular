<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceSuspendedRunnerQuotationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_approved_runner_cannot_submit_quotation(): void
    {
        $customer = $this->makeAccount(
            'ACTIVE',
            Account::RUNNER_STATUS_NOT_RUNNER
        );

        $runner = $this->makeAccount(
            'SUSPENDED',
            Account::RUNNER_STATUS_APPROVED
        );

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Request for suspended runner regression test.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '25.00',
                    'currency' => 'MYR',
                    'message' => 'This quotation must be rejected.',
                ]
            )
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('marketplace_quotations', 0);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => 'OPEN',
        ]);

        $this->assertDatabaseCount('core_tasks', 0);
    }

    private function makeAccount(
        string $status,
        string $runnerStatus
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Suspended Runner Quotation API Test',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => $status,
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
