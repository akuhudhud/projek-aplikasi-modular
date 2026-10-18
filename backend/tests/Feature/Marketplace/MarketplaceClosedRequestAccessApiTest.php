<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceClosedRequestAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_with_losing_quotation_cannot_view_awarded_request(): void
    {
        $customer = $this->makeAccount(
            Account::RUNNER_STATUS_NOT_RUNNER
        );

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
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Private location must not leak after award.',
                'pickup_location_snapshot' => [
                    'address' => 'Private pickup address',
                ],
                'delivery_location_snapshot' => [
                    'address' => 'Private delivery address',
                ],
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $winningQuote = $this->withToken($winningRunnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '20.00']
            )
            ->assertCreated();

        $losingQuote = $this->withToken($losingRunnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '25.00']
            )
            ->assertCreated();

        $winningQuotationId = $winningQuote->json(
            'data.quotation.quotation_id'
        );

        $this->assertNotEmpty(
            $losingQuote->json('data.quotation.quotation_id')
        );

        $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $winningQuotationId]
            )
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'winning_quotation_id' => $winningQuotationId,
        ]);

        $this->withToken($losingRunnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->withToken($winningRunnerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.request.pickup_location_snapshot.address',
                'Private pickup address'
            );
    }

    private function makeAccount(string $runnerStatus): Account
    {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Closed Request Access Test',
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
