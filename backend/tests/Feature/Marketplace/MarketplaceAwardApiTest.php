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

class MarketplaceAwardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_awarding_quotation_marks_competing_quotation_not_selected(): void
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
                'description' => 'Request with competing quotations.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $winningQuote = $this->withToken($runnerOneToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '20.00']
            )
            ->assertCreated();

        $winningQuotationId = $winningQuote->json(
            'data.quotation.quotation_id'
        );

        $competingQuote = $this->withToken($runnerTwoToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '24.00']
            )
            ->assertCreated();

        $competingQuotationId = $competingQuote->json(
            'data.quotation.quotation_id'
        );

        $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $winningQuotationId]
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.task.assigned_account_id',
                $runnerOne->id
            )
            ->assertJsonPath('data.task.generic_status', 'ASSIGNED');

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'winning_quotation_id' => $winningQuotationId,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $winningQuotationId,
            'status' => MarketplaceQuotation::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $competingQuotationId,
            'status' => MarketplaceQuotation::STATUS_NOT_SELECTED,
        ]);

        $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $competingQuotationId]
            )
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Another quotation has already been awarded.'
            );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'winning_quotation_id' => $winningQuotationId,
        ]);

        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_retrying_same_award_through_api_does_not_create_duplicate_core_task(): void
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
                'description' => 'Request for award retry test.',
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $quote = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '15.00']
            )
            ->assertCreated();

        $quotationId = $quote->json(
            'data.quotation.quotation_id'
        );

        $firstAward = $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $quotationId]
            )
            ->assertOk()
            ->assertJsonPath('success', true);

        $firstTaskId = $firstAward->json('data.task.task_id');

        $this->assertNotEmpty($firstTaskId);
        $this->assertDatabaseCount('core_tasks', 1);

        $retryAward = $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $quotationId]
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.task.task_id', $firstTaskId)
            ->assertJsonPath(
                'data.task.assigned_account_id',
                $runner->id
            );

        $this->assertSame(
            $firstTaskId,
            $retryAward->json('data.task.task_id')
        );

        $this->assertDatabaseCount('core_tasks', 1);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'winning_quotation_id' => $quotationId,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_ACCEPTED,
        ]);
    }

    public function test_runner_suspended_before_award_cannot_be_selected(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $request = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'service_type' => 'GENERAL_SERVICE',
                'description' => 'Runner eligibility is rechecked at award.',
            ])
            ->assertCreated();

        $requestId = $request->json('data.request.request_id');

        $quotation = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                ['amount' => '25.00']
            )
            ->assertCreated();

        $quotationId = $quotation->json(
            'data.quotation.quotation_id'
        );

        $runner->forceFill([
            'status' => 'SUSPENDED',
        ])->save();

        $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                ['quotation_id' => $quotationId]
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
            'winning_quotation_id' => null,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_SUBMITTED,
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
            'name' => 'Marketplace Award API Test',
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
