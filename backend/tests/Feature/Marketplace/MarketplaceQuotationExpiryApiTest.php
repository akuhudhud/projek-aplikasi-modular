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

class MarketplaceQuotationExpiryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_award_expired_quotation_through_api(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Request with an expired quotation.',
                'pickup_location_snapshot' => [
                    'address' => 'Pickup location',
                ],
                'delivery_location_snapshot' => [
                    'address' => 'Delivery location',
                ],
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

        MarketplaceQuotation::query()
            ->whereKey($quotationId)
            ->update([
                'expires_at' => now()->subMinute(),
            ]);

        $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $quotationId]
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'This quotation has expired.'
            );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_runner_cannot_withdraw_expired_quotation_through_api(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Request with an expired quotation.',
                'pickup_location_snapshot' => [
                    'address' => 'Pickup location',
                ],
                'delivery_location_snapshot' => [
                    'address' => 'Delivery location',
                ],
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $quote = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '18.00']
            )
            ->assertCreated();

        $quotationId = $quote->json('data.quotation.quotation_id');

        MarketplaceQuotation::query()
            ->whereKey($quotationId)
            ->update([
                'expires_at' => now()->subMinute(),
            ]);

        $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'
                .$requestId
                .'/quotations/'
                .$quotationId
                .'/withdraw'
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'This quotation has expired.'
            );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseCount('core_tasks', 0);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Quotation Expiry API Test',
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
