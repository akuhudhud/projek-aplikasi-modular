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
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Private request owned by another customer.',
                'pickup_location_snapshot' => [
                    'address' => 'Pickup location',
                ],
                'delivery_location_snapshot' => [
                    'address' => 'Delivery location',
                ],
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

    public function test_runner_who_lost_quotation_cannot_view_awarded_request(): void
    {
        $customer = $this->makeAccount();

        $winningRunner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $losingRunner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $customerToken = $this->createSessionToken($customer);
        $winningRunnerToken = $this->createSessionToken($winningRunner);
        $losingRunnerToken = $this->createSessionToken($losingRunner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Access control after quotation award.',
                'pickup_location_snapshot' => [
                    'address' => 'Private pickup location',
                ],
                'delivery_location_snapshot' => [
                    'address' => 'Private delivery location',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $requestId = $created->json('data.request.request_id');

        $this->assertNotEmpty($requestId);

        // Customer retains internal references.
        $this->withToken($customerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath(
                'data.request.customer_account_id',
                $customer->id
            )
            ->assertJsonPath(
                'data.request.target_runner_account_id',
                null
            )
            ->assertJsonPath(
                'data.request.winning_quotation_id',
                null
            );

        // Both runners may view the request while it is open,
        // but must not receive internal references.
        $this->withToken($winningRunnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('data.request.status', 'OPEN')
            ->assertJsonMissingPath(
                'data.request.customer_account_id'
            )
            ->assertJsonMissingPath(
                'data.request.target_runner_account_id'
            )
            ->assertJsonMissingPath(
                'data.request.winning_quotation_id'
            );

        $this->withToken($losingRunnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('data.request.status', 'OPEN')
            ->assertJsonMissingPath(
                'data.request.customer_account_id'
            )
            ->assertJsonMissingPath(
                'data.request.target_runner_account_id'
            )
            ->assertJsonMissingPath(
                'data.request.winning_quotation_id'
            );

        // Both runners submit quotations.
        $winningQuotation = $this->withToken($winningRunnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '20.00',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('success', true);

        $losingQuotation = $this->withToken($losingRunnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '18.00',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('success', true);

        $winningQuotationId = $winningQuotation->json(
            'data.quotation.quotation_id'
        );

        $losingQuotationId = $losingQuotation->json(
            'data.quotation.quotation_id'
        );

        $this->assertNotEmpty($winningQuotationId);
        $this->assertNotEmpty($losingQuotationId);

        // Customer awards the winning runner's quotation.
        $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                [
                    'quotation_id' => $winningQuotationId,
                ]
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.task.assigned_account_id',
                $winningRunner->id
            );

        // Customer can still see all internal references after award.
        $this->withToken($customerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath(
                'data.request.customer_account_id',
                $customer->id
            )
            ->assertJsonPath(
                'data.request.winning_quotation_id',
                $winningQuotationId
            );

        // Winning runner retains access to task locations,
        // but not internal customer/request references.
        $this->withToken($winningRunnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('data.request.status', 'AWARDED')
            ->assertJsonPath(
                'data.request.pickup_location_snapshot.address',
                'Private pickup location'
            )
            ->assertJsonPath(
                'data.request.delivery_location_snapshot.address',
                'Private delivery location'
            )
            ->assertJsonMissingPath(
                'data.request.customer_account_id'
            )
            ->assertJsonMissingPath(
                'data.request.target_runner_account_id'
            )
            ->assertJsonMissingPath(
                'data.request.winning_quotation_id'
            );

        // Losing runner must no longer access the request.
        $this->withToken($losingRunnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => 'AWARDED',
            'winning_quotation_id' => $winningQuotationId,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $winningQuotationId,
            'status' => 'ACCEPTED',
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $losingQuotationId,
            'status' => 'NOT_SELECTED',
        ]);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Request Access API Test',
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
