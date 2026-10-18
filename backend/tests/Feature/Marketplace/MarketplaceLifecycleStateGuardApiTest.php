<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceLifecycleStateGuardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_awarded_request_cannot_be_cancelled_and_state_remains_consistent(): void
    {
        [$customer, $runner, $requestId, $quotationId, $customerToken, $runnerToken] =
            $this->createAwardedRequest();

        $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests/'.$requestId.'/cancel')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'winning_quotation_id' => $quotationId,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_accepted_quotation_cannot_be_withdrawn_and_state_remains_consistent(): void
    {
        [$customer, $runner, $requestId, $quotationId, $customerToken, $runnerToken] =
            $this->createAwardedRequest();

        $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'
                .$requestId
                .'/quotations/'
                .$quotationId
                .'/withdraw'
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'winning_quotation_id' => $quotationId,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_request_expires_at_exact_boundary_and_submitted_quotation_is_expired(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Request for expiry boundary regression.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $quote = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '20.00']
            )
            ->assertCreated();

        $quotationId = $quote->json('data.quotation.quotation_id');

        $expiresAt = now()->addMinute()->startOfSecond();

        MarketplaceRequest::query()
            ->where('request_id', $requestId)
            ->update(['expires_at' => $expiresAt]);

        $this->travelTo($expiresAt);

        $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests/'.$requestId.'/cancel')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseCount('core_tasks', 0);
    }

    /**
     * Create and award a request, returning its actors, identifiers and tokens.
     *
     * @return array{
     *     0: Account,
     *     1: Account,
     *     2: string,
     *     3: string,
     *     4: string,
     *     5: string
     * }
     */
    private function createAwardedRequest(): array
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Request for lifecycle state guard regression.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $quote = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '25.00']
            )
            ->assertCreated();

        $quotationId = $quote->json('data.quotation.quotation_id');

        $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $quotationId]
            )
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('core_tasks', 1);

        return [
            $customer,
            $runner,
            $requestId,
            $quotationId,
            $customerToken,
            $runnerToken,
        ];
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Lifecycle State Guard Test',
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
