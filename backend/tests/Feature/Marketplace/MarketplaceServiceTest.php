<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Models\RunnerPublicProfile;
use App\Services\MarketplaceService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceService $marketplace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marketplace = $this->app->make(MarketplaceService::class);
    }

    public function test_request_stays_open_until_a_quotation_is_awarded(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Collect and deliver an item.',
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            25.00
        );

        $this->assertSame(
            MarketplaceRequest::STATUS_OPEN,
            $request->fresh()->status
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_SUBMITTED,
            $quotation->status
        );

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_awarding_quote_assigns_core_task_and_closes_competing_quotes(): void
    {
        $customer = $this->makeAccount();
        $runnerOne = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );
        $runnerTwo = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Deliver a parcel.',
        ]);

        $winningQuote = $this->marketplace->submitQuotation(
            $runnerOne,
            $request->request_id,
            20.00
        );

        $otherQuote = $this->marketplace->submitQuotation(
            $runnerTwo,
            $request->request_id,
            24.00
        );

        $task = $this->marketplace->awardQuotation(
            $customer,
            $request->request_id,
            $winningQuote->quotation_id
        );

        $this->assertSame('Marketplace', $task->module_key);
        $this->assertSame(
            'MARKETPLACE_SERVICE_REQUEST',
            $task->task_type
        );
        $this->assertSame($runnerOne->id, $task->assigned_account_id);
        $this->assertSame(CoreTask::STATUS_ASSIGNED, $task->generic_status);

        $this->assertSame(
            MarketplaceRequest::STATUS_AWARDED,
            $request->fresh()->status
        );

        $this->assertSame(
            $winningQuote->quotation_id,
            $request->fresh()->winning_quotation_id
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_ACCEPTED,
            $winningQuote->fresh()->status
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_NOT_SELECTED,
            $otherQuote->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_same_award_retry_returns_existing_task(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Deliver documents.',
        ]);

        $quote = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            15.00
        );

        $firstTask = $this->marketplace->awardQuotation(
            $customer,
            $request->request_id,
            $quote->quotation_id
        );

        $retryTask = $this->marketplace->awardQuotation(
            $customer,
            $request->request_id,
            $quote->quotation_id
        );

        $this->assertSame($firstTask->task_id, $retryTask->task_id);
        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_unapproved_runner_cannot_submit_a_quotation(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_PENDING
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Collect a package.',
        ]);

        $this->expectException(DomainException::class);

        $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            10.00
        );
    }

    public function test_runner_who_loses_approval_cannot_be_awarded_a_quotation(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Deliver an item after runner approval changes.',
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            21.00
        );

        $runner->forceFill([
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ])->save();

        try {
            $this->marketplace->awardQuotation(
                $customer,
                $request->request_id,
                $quotation->quotation_id
            );

            $this->fail(
                'A runner who is no longer approved must not receive the task.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'The quoted runner is no longer active and approved.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            MarketplaceRequest::STATUS_OPEN,
            $request->fresh()->status
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_SUBMITTED,
            $quotation->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_suspended_runner_cannot_be_awarded_a_quotation(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Request must not be assigned to a suspended account.',
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            23.00
        );

        $runner->forceFill([
            'status' => 'SUSPENDED',
        ])->save();

        try {
            $this->marketplace->awardQuotation(
                $customer,
                $request->request_id,
                $quotation->quotation_id
            );

            $this->fail(
                'A suspended runner must not receive a task.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'The quoted runner is no longer active and approved.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            MarketplaceRequest::STATUS_OPEN,
            $request->fresh()->status
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_SUBMITTED,
            $quotation->fresh()->status
        );

        $this->assertSame(
            'SUSPENDED',
            $runner->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_quotation_from_another_request_cannot_be_awarded(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );

        $firstRequest = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'First independent customer request.',
        ]);

        $secondRequest = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Second independent customer request.',
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $secondRequest->request_id,
            31.00
        );

        try {
            $this->marketplace->awardQuotation(
                $customer,
                $firstRequest->request_id,
                $quotation->quotation_id
            );

            $this->fail(
                'A quotation belonging to another request must not be awarded.'
            );
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(
                MarketplaceQuotation::class,
                $exception->getModel()
            );
        }

        $this->assertSame(
            MarketplaceRequest::STATUS_OPEN,
            $firstRequest->fresh()->status
        );

        $this->assertSame(
            MarketplaceRequest::STATUS_OPEN,
            $secondRequest->fresh()->status
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_SUBMITTED,
            $quotation->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_only_request_owner_can_award_a_quotation(): void
    {
        $customer = $this->makeAccount();
        $otherCustomer = $this->makeAccount();
        $runner = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Deliver an item.',
        ]);

        $quote = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            12.00
        );

        $this->expectException(DomainException::class);

        $this->marketplace->awardQuotation(
            $otherCustomer,
            $request->request_id,
            $quote->quotation_id
        );
    }

    public function test_runner_profile_request_only_accepts_target_runner_quote(): void
    {
        $customer = $this->makeAccount();
        $targetRunner = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );
        $otherRunner = $this->makeAccount(
            runnerStatus: Account::RUNNER_STATUS_APPROVED
        );

        $this->createPublicProfile(
            $targetRunner,
            'target-runner-profile'
        );

        $request = $this->marketplace->createRequest($customer, [
            'source_type' => MarketplaceRequest::SOURCE_RUNNER_PROFILE_LINK,
            'target_runner_slug' => 'target-runner-profile',
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Request through runner profile.',
        ]);

        $this->assertSame(
            $targetRunner->id,
            $request->target_runner_account_id
        );
        $this->assertSame(
            MarketplaceRequest::STATUS_OPEN,
            $request->status
        );
        $this->assertDatabaseCount('core_tasks', 0);

        try {
            $this->marketplace->submitQuotation(
                $otherRunner,
                $request->request_id,
                18.00
            );

            $this->fail('A different runner must not quote this request.');
        } catch (DomainException $exception) {
            $this->assertSame(
                'This request is targeted at another runner.',
                $exception->getMessage()
            );
        }

        $quotation = $this->marketplace->submitQuotation(
            $targetRunner,
            $request->request_id,
            19.00
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_SUBMITTED,
            $quotation->status
        );
        $this->assertDatabaseCount('core_tasks', 0);
    }

    private function createPublicProfile(
        Account $runner,
        string $slug
    ): RunnerPublicProfile {
        return RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => $slug,
            'display_name' => 'Test Runner',
            'service_area' => 'Tuaran',
            'services' => ['General service'],
            'is_published' => true,
        ]);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Test Account',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => $runnerStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }
}
