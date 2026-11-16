<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Services\MarketplaceService;
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

        $this->marketplace = $this->app->make(MarketplaceService::class);
    }

    public function test_expired_request_and_submitted_quotes_transition_to_expired(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->marketplace->createRequest($customer, [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Request that has passed its deadline.',
            'pickup_location_snapshot' => ['address' => 'Pickup location'],
            'delivery_location_snapshot' => ['address' => 'Delivery location'],
            'expires_at' => now()->addHours(2),
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

    public function test_quotation_does_not_expire_independently_while_request_remains_open(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->marketplace->createRequest($customer, [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Quotation expiry must follow parent request.',
            'pickup_location_snapshot' => ['address' => 'Pickup location'],
            'delivery_location_snapshot' => ['address' => 'Delivery location'],
            'expires_at' => now()->addHours(2),
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
            MarketplaceQuotation::STATUS_SUBMITTED,
            $quotation->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_quotation_with_past_legacy_expiry_can_be_awarded(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->marketplace->createRequest($customer, [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Legacy quotation expiry does not block award.',
            'pickup_location_snapshot' => ['address' => 'Pickup location'],
            'delivery_location_snapshot' => ['address' => 'Delivery location'],
            'expires_at' => now()->addHours(2),
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            22.00
        );

        $quotation->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        $task = $this->marketplace->awardQuotation(
            $customer,
            $request->request_id,
            $quotation->quotation_id
        );

        $this->assertNotNull($task);

        $this->assertSame(
            MarketplaceRequest::STATUS_AWARDED,
            $request->fresh()->status
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_ACCEPTED,
            $quotation->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_quotation_with_past_legacy_expiry_can_be_withdrawn(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->marketplace->createRequest($customer, [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Legacy quotation expiry does not block withdrawal.',
            'pickup_location_snapshot' => ['address' => 'Pickup location'],
            'delivery_location_snapshot' => ['address' => 'Delivery location'],
            'expires_at' => now()->addHours(2),
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            17.00
        );

        $quotation->forceFill([
            'expires_at' => now()->subMinute(),
        ])->save();

        $withdrawn = $this->marketplace->withdrawQuotation(
            $runner,
            $request->request_id,
            $quotation->quotation_id
        );

        $this->assertSame(
            MarketplaceQuotation::STATUS_WITHDRAWN,
            $withdrawn->status
        );

        $this->assertSame(
            MarketplaceRequest::STATUS_OPEN,
            $request->fresh()->status
        );

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_expired_request_cannot_be_awarded(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->marketplace->createRequest($customer, [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Expired request cannot be awarded.',
            'pickup_location_snapshot' => ['address' => 'Pickup location'],
            'delivery_location_snapshot' => ['address' => 'Delivery location'],
            'expires_at' => now()->addHours(2),
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

            $this->fail('An expired request must not be awarded.');
        } catch (\DomainException $exception) {
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
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->marketplace->createRequest($customer, [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Expired request cannot accept quotes.',
            'pickup_location_snapshot' => ['address' => 'Pickup location'],
            'delivery_location_snapshot' => ['address' => 'Delivery location'],
            'expires_at' => now()->addHours(2),
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

            $this->fail('An expired request must not accept quotations.');
        } catch (\DomainException $exception) {
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

    public function test_open_request_without_expiry_is_expired_and_submitted_quotations_are_expired(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->marketplace->createRequest($customer, [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Legacy request without an expiry.',
            'pickup_location_snapshot' => ['address' => 'Pickup location'],
            'delivery_location_snapshot' => ['address' => 'Delivery location'],
            'expires_at' => now()->addHours(2),
        ]);

        $quotation = $this->marketplace->submitQuotation(
            $runner,
            $request->request_id,
            25.00
        );

        $request->forceFill([
            'expires_at' => null,
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

    public function test_request_without_expiry_cannot_accept_new_quotations(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->marketplace->createRequest($customer, [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Legacy request cannot accept quotations.',
            'pickup_location_snapshot' => ['address' => 'Pickup location'],
            'delivery_location_snapshot' => ['address' => 'Delivery location'],
            'expires_at' => now()->addHours(2),
        ]);

        $request->forceFill([
            'expires_at' => null,
        ])->save();

        try {
            $this->marketplace->submitQuotation(
                $runner,
                $request->request_id,
                15.00
            );

            $this->fail(
                'A request without an expiry must not accept quotations.'
            );
        } catch (\DomainException $exception) {
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
            'profile_name' => 'Marketplace Expiry Test',
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
