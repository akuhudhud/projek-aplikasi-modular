<?php

namespace Tests\Feature\Marketplace;

use App\Contracts\CoreTaskRegistry;
use App\Models\Account;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Services\MarketplaceService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceExpiryServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceService $marketplace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marketplace = new MarketplaceService(
            $this->app->make(CoreTaskRegistry::class)
        );
    }

    public function test_expired_request_and_submitted_quotes_transition_to_expired(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Request that has passed its deadline.',
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            25.00
        );

        $request->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        $this->marketplace->expireDueItems();

        $this->assertSame(
            MarketplaceRequest::STATUS_EXPIRED,
            $request->fresh()->status
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_EXPIRED,
            $quotation->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_expired_quotation_transitions_to_expired_while_request_remains_open(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Request with an expired quotation.',
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            18.00
        );

        $quotation->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        $this->marketplace->expireDueItems();

        $this->assertSame(
            MarketplaceRequest::STATUS_OPEN,
            $request->fresh()->status
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_EXPIRED,
            $quotation->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_expired_request_cannot_be_awarded(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Expired request cannot be awarded.',
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            20.00
        );

        $request->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        try {
            $this->marketplace->awardQuotation(
                $customer,
                $request->request_id,
                $quotation->quotation_id
            );

            $this->fail(
                'An expired request must not be awarded.'
            );
        } catch (DomainException $exception) {
            $this->assertStringContainsString(
                'cannot be awarded',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            MarketplaceRequest::STATUS_EXPIRED,
            $request->fresh()->status
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_EXPIRED,
            $quotation->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_expired_request_cannot_accept_new_quotations(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $request = $this->marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Expired request cannot accept quotes.',
        ]);

        $request->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        try {
            $this->marketplace->submitQuotation(
                $runner,
                $request->request_id,
                15.00
            );

            $this->fail(
                'An expired request must not accept quotations.'
            );
        } catch (DomainException $exception) {
            $this->assertStringContainsString(
                'no longer accepting quotations',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            MarketplaceRequest::STATUS_EXPIRED,
            $request->fresh()->status
        );

        $this->assertDatabaseCount('marketplace_quotations', 0);
        $this->assertDatabaseCount('core_tasks', 0);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Expiry Test',
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
