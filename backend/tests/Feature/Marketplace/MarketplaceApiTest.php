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

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_api_requires_an_authenticated_session(): void
    {
        $this->getJson('/api/marketplace/requests')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_create_and_view_own_request(): void
    {
        $customer = $this->makeAccount();
        $token = $this->createSessionToken($customer);

        $response = $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Collect and deliver a parcel.',
                'pickup_location_snapshot' => [
                    'label' => 'Point A',
                ],
                'delivery_location_snapshot' => [
                    'label' => 'Point B',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.request.customer_account_id',
                $customer->id
            )
            ->assertJsonPath('data.request.status', 'OPEN');

        $requestId = $response->json('data.request.request_id');

        $this->withToken($token)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath(
                'data.request.request_id',
                $requestId
            );
    }

    public function test_approved_runner_can_quote_and_customer_can_award(): void
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
                'description' => 'Deliver a document.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $quotationResponse = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '18.50',
                    'currency' => 'MYR',
                    'message' => 'I can complete this delivery.',
                ]
            );

        $quotationResponse
            ->assertCreated()
            ->assertJsonPath('data.quotation.status', 'SUBMITTED')
            ->assertJsonPath(
                'data.quotation.runner_account_id',
                $runner->id
            );

        $quotationId = $quotationResponse->json(
            'data.quotation.quotation_id'
        );

        $this->withToken($customerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath(
                'data.request.quotations.0.quotation_id',
                $quotationId
            );

        $awardResponse = $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $quotationId]
            );

        $awardResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.task.assigned_account_id',
                $runner->id
            )
            ->assertJsonPath(
                'data.task.generic_status',
                'ASSIGNED'
            );

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

    public function test_customer_cannot_award_another_customers_request(): void
    {
        $owner = $this->makeAccount();
        $otherCustomer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $ownerToken = $this->createSessionToken($owner);
        $otherToken = $this->createSessionToken($otherCustomer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($ownerToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Deliver a package.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $quote = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '12.00']
            )
            ->assertCreated();

        $quotationId = $quote->json('data.quotation.quotation_id');

        $this->withToken($otherToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $quotationId]
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_runner_cannot_browse_available_requests_without_approval(): void
    {
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_PENDING
        );

        $token = $this->createSessionToken($runner);

        $this->withToken($token)
            ->getJson('/api/marketplace/requests?view=available')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_runner_cannot_view_an_unrelated_targeted_request(): void
    {
        $customer = $this->makeAccount();
        $targetRunner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );
        $otherRunner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $customerToken = $this->createSessionToken($customer);
        $otherRunnerToken = $this->createSessionToken($otherRunner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'source_type' => 'runner_profile_link',
                'target_runner_account_id' => $targetRunner->id,
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Request for a specific runner.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $this->withToken($otherRunnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertNotFound();
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace API Test',
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
