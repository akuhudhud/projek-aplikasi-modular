<?php

namespace Tests\Feature\Marketplace;

use App\Contracts\CoreTaskAssigner;
use App\Contracts\CoreTaskRegistry;
use App\Models\Account;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Services\MarketplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MarketplaceAwardRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_assignment_rolls_back_entire_marketplace_award(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $registry = $this->app->make(CoreTaskRegistry::class);
        $normalAssigner = $this->app->make(CoreTaskAssigner::class);

        $marketplace = new MarketplaceService(
            $registry,
            $normalAssigner
        );

        $request = $marketplace->createRequest($customer, [
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Request for award rollback verification.',
        ]);

        $quotation = $marketplace->submitQuotation(
            $runner,
            $request->request_id,
            25.00
        );

        $failingAssigner = \Mockery::mock(CoreTaskAssigner::class);

        $failingAssigner
            ->shouldReceive('assign')
            ->once()
            ->andThrow(
                new RuntimeException('Simulated assignment failure.')
            );

        $marketplaceWithFailingAssigner = new MarketplaceService(
            $registry,
            $failingAssigner
        );

        try {
            $marketplaceWithFailingAssigner->awardQuotation(
                $customer,
                $request->request_id,
                $quotation->quotation_id
            );

            $this->fail(
                'The award must fail when Core Task assignment fails.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated assignment failure.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $request->request_id,
            'status' => MarketplaceRequest::STATUS_OPEN,
            'winning_quotation_id' => null,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotation->quotation_id,
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
            'name' => 'Marketplace Rollback Test',
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
