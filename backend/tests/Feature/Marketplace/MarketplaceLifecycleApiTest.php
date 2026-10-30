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

class MarketplaceLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_own_open_request(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Request to be cancelled.',
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
                ['amount' => '15.00']
            )
            ->assertCreated();

        $quotationId = $quote->json('data.quotation.quotation_id');

        $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/cancel'
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', 'CANCELLED');

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_NOT_SELECTED,
        ]);

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_non_owner_cannot_cancel_request(): void
    {
        $owner = $this->makeAccount();
        $otherCustomer = $this->makeAccount();

        $ownerToken = $this->createSessionToken($owner);
        $otherToken = $this->createSessionToken($otherCustomer);

        $created = $this->withToken($ownerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Request owned by another customer.',
                'pickup_location_snapshot' => [
                    'address' => 'Pickup location',
                ],
                'delivery_location_snapshot' => [
                    'address' => 'Delivery location',
                ],
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $this->withToken($otherToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/cancel'
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);
    }

    public function test_runner_can_withdraw_own_submitted_quotation(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Request with a withdrawable quote.',
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
                ['amount' => '22.50']
            )
            ->assertCreated();

        $quotationId = $quote->json('data.quotation.quotation_id');

        $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'
                .$requestId
                .'/quotations/'
                .$quotationId
                .'/withdraw'
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quotation.status', 'WITHDRAWN');

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_WITHDRAWN,
        ]);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);
    }

    public function test_runner_can_resubmit_after_withdrawing_previous_quotation(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Request for quotation resubmission.',
                'pickup_location_snapshot' => [
                    'address' => 'Pickup location',
                ],
                'delivery_location_snapshot' => [
                    'address' => 'Delivery location',
                ],
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $firstQuote = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '22.50']
            )
            ->assertCreated();

        $firstQuotationId = $firstQuote->json(
            'data.quotation.quotation_id'
        );

        $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'
                .$requestId
                .'/quotations/'
                .$firstQuotationId
                .'/withdraw'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.quotation.status',
                'WITHDRAWN'
            );

        $secondQuote = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '25.00',
                    'message' => 'Updated quotation.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.quotation.status',
                'SUBMITTED'
            );

        $secondQuotationId = $secondQuote->json(
            'data.quotation.quotation_id'
        );

        $this->assertNotSame(
            $firstQuotationId,
            $secondQuotationId
        );

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $firstQuotationId,
            'request_id' => $requestId,
            'runner_account_id' => $runner->id,
            'status' => MarketplaceQuotation::STATUS_WITHDRAWN,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $secondQuotationId,
            'request_id' => $requestId,
            'runner_account_id' => $runner->id,
            'status' => MarketplaceQuotation::STATUS_SUBMITTED,
        ]);

        $this->assertSame(
            1,
            MarketplaceQuotation::query()
                ->where('request_id', $requestId)
                ->where('runner_account_id', $runner->id)
                ->where(
                    'status',
                    MarketplaceQuotation::STATUS_SUBMITTED
                )
                ->count()
        );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);
    }

    public function test_runner_cannot_withdraw_another_runners_quotation(): void
    {
        $customer = $this->makeAccount();
        $runnerOne = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);
        $runnerTwo = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $runnerOneToken = $this->createSessionToken($runnerOne);
        $runnerTwoToken = $this->createSessionToken($runnerTwo);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Request with another runner quote.',
                'pickup_location_snapshot' => [
                    'address' => 'Pickup location',
                ],
                'delivery_location_snapshot' => [
                    'address' => 'Delivery location',
                ],
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $quote = $this->withToken($runnerOneToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '19.00']
            )
            ->assertCreated();

        $quotationId = $quote->json('data.quotation.quotation_id');

        $this->withToken($runnerTwoToken)
            ->postJson(
                '/api/marketplace/requests/'
                .$requestId
                .'/quotations/'
                .$quotationId
                .'/withdraw'
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_SUBMITTED,
        ]);
    }

    public function test_expired_request_and_its_quotes_are_updated_before_show(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);
        $customerToken = $this->createSessionToken($customer);

        $requestId = (string) Str::uuid();
        $quotationId = (string) Str::uuid();
        $expiredAt = now()->subMinute();
        $now = now();

        MarketplaceRequest::query()->create([
            'request_id' => $requestId,
            'customer_account_id' => $customer->id,
            'source_type' => MarketplaceRequest::SOURCE_MARKETPLACE,
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Expired marketplace request.',
            'status' => MarketplaceRequest::STATUS_OPEN,
            'expires_at' => $expiredAt,
        ]);

        MarketplaceQuotation::query()->create([
            'quotation_id' => $quotationId,
            'request_id' => $requestId,
            'runner_account_id' => $runner->id,
            'amount' => '16.00',
            'currency' => 'MYR',
            'status' => MarketplaceQuotation::STATUS_SUBMITTED,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->withToken($customerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('data.request.status', 'EXPIRED')
            ->assertJsonPath(
                'data.request.quotations.0.status',
                'EXPIRED'
            );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_EXPIRED,
        ]);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Lifecycle Test',
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
