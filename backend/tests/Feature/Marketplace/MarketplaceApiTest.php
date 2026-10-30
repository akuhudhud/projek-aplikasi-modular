<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Models\RunnerPublicProfile;
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
                'task_class' => 'PICKUP_DELIVERY',
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
            ->assertJsonPath('data.request.status', 'OPEN')
            ->assertJsonPath('data.request.source_type', 'marketplace')
            ->assertJsonPath('data.request.target_runner_account_id', null);

        $requestId = $response->json('data.request.request_id');

        $this->withToken($token)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('data.request.request_id', $requestId);
    }

    public function test_approved_runner_can_quote_and_customer_can_award(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Deliver a document.',
                'pickup_location_snapshot' => [
                    'label' => 'Document pickup',
                ],
                'delivery_location_snapshot' => [
                    'label' => 'Document delivery',
                ],
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
            ->assertJsonPath('data.task.assigned_account_id', $runner->id)
            ->assertJsonPath('data.task.generic_status', 'ASSIGNED');

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
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $ownerToken = $this->createSessionToken($owner);
        $otherToken = $this->createSessionToken($otherCustomer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($ownerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Deliver a package.',
                'pickup_location_snapshot' => [
                    'label' => 'Package pickup',
                ],
                'delivery_location_snapshot' => [
                    'label' => 'Package delivery',
                ],
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
        $runner = $this->makeAccount(Account::RUNNER_STATUS_PENDING);
        $token = $this->createSessionToken($runner);

        $this->withToken($token)
            ->getJson('/api/marketplace/requests?view=available')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_profile_link_request_resolves_public_slug_and_only_target_runner_can_quote(): void
    {
        $customer = $this->makeAccount();
        $targetRunner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);
        $otherRunner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $targetRunnerToken = $this->createSessionToken($targetRunner);
        $otherRunnerToken = $this->createSessionToken($otherRunner);

        $this->createPublicProfile($targetRunner, 'target-runner-profile');

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'source_type' => 'runner_profile_link',
                'target_runner_slug' => 'target-runner-profile',
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Request for a specific runner.',
                'pickup_location_snapshot' => [
                    'label' => 'Specific runner pickup',
                ],
                'delivery_location_snapshot' => [
                    'label' => 'Specific runner delivery',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.request.source_type',
                'runner_profile_link'
            )
            ->assertJsonPath(
                'data.request.target_runner_account_id',
                $targetRunner->id
            )
            ->assertJsonPath('data.request.status', 'OPEN');

        $requestId = $created->json('data.request.request_id');

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'source_type' => 'runner_profile_link',
            'target_runner_account_id' => $targetRunner->id,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);

        $this->withToken($otherRunnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertNotFound();

        $this->withToken($otherRunnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '20.00']
            )
            ->assertUnprocessable();

        $this->withToken($targetRunnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '18.00']
            )
            ->assertCreated()
            ->assertJsonPath('data.quotation.status', 'SUBMITTED');
    }

    public function test_request_cannot_target_an_unpublished_runner_profile(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);
        $token = $this->createSessionToken($customer);

        $this->createPublicProfile(
            $runner,
            'unpublished-runner-profile',
            false
        );

        $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'source_type' => 'runner_profile_link',
                'target_runner_slug' => 'unpublished-runner-profile',
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'This request must be rejected.',
                'pickup_location_snapshot' => [
                    'label' => 'Pickup location',
                ],
                'delivery_location_snapshot' => [
                    'label' => 'Delivery location',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('marketplace_requests', 0);
    }

    public function test_profile_link_request_requires_a_public_profile_slug(): void
    {
        $customer = $this->makeAccount();
        $token = $this->createSessionToken($customer);

        $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'source_type' => 'runner_profile_link',
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Missing profile slug.',
                'pickup_location_snapshot' => [
                    'label' => 'Pickup location',
                ],
                'delivery_location_snapshot' => [
                    'label' => 'Delivery location',
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('marketplace_requests', 0);
    }

    public function test_marketplace_request_cannot_include_a_runner_profile_slug(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);
        $token = $this->createSessionToken($customer);

        $this->createPublicProfile($runner, 'marketplace-invalid-target');

        $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'source_type' => 'marketplace',
                'target_runner_slug' => 'marketplace-invalid-target',
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Marketplace requests cannot target a runner.',
                'pickup_location_snapshot' => [
                    'label' => 'Pickup location',
                ],
                'delivery_location_snapshot' => [
                    'label' => 'Delivery location',
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('marketplace_requests', 0);
    }

    private function createPublicProfile(
        Account $runner,
        string $slug,
        bool $published = true
    ): RunnerPublicProfile {
        return RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => $slug,
            'display_name' => 'Test Runner',
            'service_area' => 'Tuaran',
            'services' => ['General service'],
            'is_published' => $published,
        ]);
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
