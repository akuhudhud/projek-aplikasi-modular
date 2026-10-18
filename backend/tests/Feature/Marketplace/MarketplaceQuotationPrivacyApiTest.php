<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceQuotationPrivacyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_can_only_view_own_quotations_on_another_customers_request(): void
    {
        $customer = $this->makeAccount();
        $runnerOne = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );
        $runnerTwo = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $customerToken = $this->createSessionToken($customer);
        $runnerOneToken = $this->createSessionToken($runnerOne);
        $runnerTwoToken = $this->createSessionToken($runnerTwo);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Request with private runner quotations.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $firstQuote = $this->withToken($runnerOneToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '21.00',
                    'message' => 'Private quotation from runner one.',
                ]
            )
            ->assertCreated();

        $firstQuotationId = $firstQuote->json(
            'data.quotation.quotation_id'
        );

        $secondQuote = $this->withToken($runnerTwoToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '25.00',
                    'message' => 'Private quotation from runner two.',
                ]
            )
            ->assertCreated();

        $secondQuotationId = $secondQuote->json(
            'data.quotation.quotation_id'
        );

        $runnerResponse = $this->withToken($runnerTwoToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.request.request_id',
                $requestId
            )
            ->assertJsonPath(
                'data.request.my_quotations.0.quotation_id',
                $secondQuotationId
            );

        $this->assertNull(
            $runnerResponse->json('data.request.quotations')
        );

        $runnerResponse->assertJsonMissing([
            'quotation_id' => $firstQuotationId,
        ]);

        $runnerResponse->assertJsonMissing([
            'message' => 'Private quotation from runner one.',
        ]);

        $customerResponse = $this->withToken($customerToken)
            ->getJson('/api/marketplace/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $customerQuotationIds = collect(
            $customerResponse->json('data.request.quotations')
        )->pluck('quotation_id')->all();

        $this->assertContains(
            $firstQuotationId,
            $customerQuotationIds
        );

        $this->assertContains(
            $secondQuotationId,
            $customerQuotationIds
        );
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Quotation Privacy API Test',
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
