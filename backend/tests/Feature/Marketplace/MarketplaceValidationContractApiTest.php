<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceValidationContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_request_rejects_missing_required_fields(): void
    {
        $customer = $this->makeAccount();
        $token = $this->createSessionToken($customer);

        $this->withToken($token)
            ->postJson('/api/marketplace/requests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'service_type',
                'description',
            ]);

        $this->assertDatabaseCount('marketplace_requests', 0);
        $this->assertDatabaseCount('marketplace_quotations', 0);
        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_submit_quotation_rejects_invalid_fields_without_persisting_quote(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Request for API validation testing.',
            ])
            ->assertCreated();

        $requestId = $created->json(
            'data.request.request_id'
        );

        $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'
                .$requestId
                .'/quotations',
                [
                    'amount' => '12.345',
                    'currency' => '12!',
                    'message' => str_repeat('x', 5001),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'amount',
                'currency',
                'message',
            ]);

        $this->assertDatabaseCount('marketplace_quotations', 0);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
            'winning_quotation_id' => null,
        ]);

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_award_rejects_invalid_quotation_id_without_mutating_request(): void
    {
        $customer = $this->makeAccount();
        $token = $this->createSessionToken($customer);

        $created = $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Request for award validation testing.',
            ])
            ->assertCreated();

        $requestId = $created->json(
            'data.request.request_id'
        );

        $this->withToken($token)
            ->postJson(
                '/api/marketplace/requests/'
                .$requestId
                .'/award',
                [
                    'quotation_id' => 'not-a-uuid',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'quotation_id',
            ]);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
            'winning_quotation_id' => null,
        ]);

        $this->assertDatabaseCount('marketplace_quotations', 0);
        $this->assertDatabaseCount('core_tasks', 0);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Validation API Test',
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
