<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use App\Notifications\MarketplaceEventNotification;
use App\Services\MarketplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_quotation_notifies_customer_and_award_notifies_runner(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $created = $this->withToken($customerToken)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Ujian notifikasi sebutharga.',
                'pickup_location_snapshot' => [
                    'address' => 'Lokasi pengambilan',
                ],
                'delivery_location_snapshot' => [
                    'address' => 'Lokasi penghantaran',
                ],
                'expires_at' => now()->addHours(2)->toISOString(),
            ])
            ->assertCreated();

        $requestId = $created->json('data.request.request_id');

        $quotation = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '18.50',
                    'currency' => 'MYR',
                    'message' => 'Saya boleh melaksanakan tugasan ini.',
                ]
            )
            ->assertCreated();

        $quotationId = $quotation->json(
            'data.quotation.quotation_id'
        );

        $customerNotification = $customer->notifications()
            ->where('type', MarketplaceEventNotification::class)
            ->latest()
            ->first();

        $this->assertNotNull($customerNotification);

        $this->assertSame(
            'marketplace.quotation_received',
            $customerNotification->data['event']
        );

        $this->assertSame(
            'Sebutharga baharu diterima',
            $customerNotification->data['title']
        );

        $this->assertSame(
            $requestId,
            $customerNotification->data['request_id']
        );

        $this->assertSame(
            $quotationId,
            $customerNotification->data['quotation_id']
        );

        $this->withToken($customerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/award',
                [
                    'quotation_id' => $quotationId,
                ]
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.task.assigned_account_id',
                $runner->id
            );

        $runnerNotification = $runner->notifications()
            ->where('type', MarketplaceEventNotification::class)
            ->latest()
            ->first();

        $this->assertNotNull($runnerNotification);

        $this->assertSame(
            'marketplace.quotation_accepted',
            $runnerNotification->data['event']
        );

        $this->assertSame(
            'Sebutharga anda dipilih',
            $runnerNotification->data['title']
        );

        $this->assertSame(
            $requestId,
            $runnerNotification->data['request_id']
        );

        $this->assertSame(
            $quotationId,
            $runnerNotification->data['quotation_id']
        );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'winning_quotation_id' => $quotationId,
        ]);

        $this->assertSame(
            1,
            $customer->notifications()
                ->where('type', MarketplaceEventNotification::class)
                ->count()
        );

        $this->assertSame(
            1,
            $runner->notifications()
                ->where('type', MarketplaceEventNotification::class)
                ->count()
        );
    }

    public function test_customer_cancellation_notifies_runner_who_submitted_quotation(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);
        $otherRunner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->makeRequest($customer);

        $this->makeQuotation($request, $runner);

        app(MarketplaceService::class)->cancelRequest(
            $customer,
            $request->request_id
        );

        $runnerNotification = $runner->notifications()
            ->where('type', MarketplaceEventNotification::class)
            ->latest()
            ->first();

        $this->assertNotNull($runnerNotification);

        $this->assertSame(
            'marketplace.request_cancelled',
            $runnerNotification->data['event']
        );

        $this->assertSame(
            $request->request_id,
            $runnerNotification->data['request_id']
        );

        $this->assertFalse(
            $otherRunner->notifications()
                ->where('type', MarketplaceEventNotification::class)
                ->exists()
        );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $request->request_id,
            'status' => MarketplaceRequest::STATUS_CANCELLED,
        ]);
    }

    public function test_expired_request_notifies_customer_and_runner_who_submitted_quotation(): void
    {
        $customer = $this->makeAccount();
        $runner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);
        $otherRunner = $this->makeAccount(Account::RUNNER_STATUS_APPROVED);

        $request = $this->makeRequest(
            $customer,
            now()->subMinute()
        );

        $this->makeQuotation($request, $runner);

        app(MarketplaceService::class)->expireDueItems();

        $customerNotification = $customer->notifications()
            ->where('type', MarketplaceEventNotification::class)
            ->latest()
            ->first();

        $this->assertNotNull($customerNotification);

        $this->assertSame(
            'marketplace.request_expired',
            $customerNotification->data['event']
        );

        $this->assertSame(
            $request->request_id,
            $customerNotification->data['request_id']
        );

        $runnerNotification = $runner->notifications()
            ->where('type', MarketplaceEventNotification::class)
            ->latest()
            ->first();

        $this->assertNotNull($runnerNotification);

        $this->assertSame(
            'marketplace.request_expired',
            $runnerNotification->data['event']
        );

        $this->assertFalse(
            $otherRunner->notifications()
                ->where('type', MarketplaceEventNotification::class)
                ->exists()
        );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $request->request_id,
            'status' => MarketplaceRequest::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'request_id' => $request->request_id,
            'runner_account_id' => $runner->id,
            'status' => MarketplaceQuotation::STATUS_EXPIRED,
        ]);
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'profile_name' => 'Marketplace Notification Test',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => $runnerStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }

    private function makeRequest(
        Account $customer,
        $expiresAt = null
    ): MarketplaceRequest {
        return MarketplaceRequest::query()->create([
            'request_id' => (string) Str::uuid(),
            'customer_account_id' => $customer->id,
            'target_runner_account_id' => null,
            'source_type' => MarketplaceRequest::SOURCE_MARKETPLACE,
            'task_class' => MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY,
            'description' => 'Ujian notifikasi permintaan.',
            'pickup_location_snapshot' => [
                'address' => 'Lokasi pengambilan',
            ],
            'delivery_location_snapshot' => [
                'address' => 'Lokasi penghantaran',
            ],
            'task_location_snapshot' => null,
            'status' => MarketplaceRequest::STATUS_OPEN,
            'expires_at' => $expiresAt ?? now()->addHours(2),
        ]);
    }

    private function makeQuotation(
        MarketplaceRequest $request,
        Account $runner
    ): MarketplaceQuotation {
        return MarketplaceQuotation::query()->create([
            'quotation_id' => (string) Str::uuid(),
            'request_id' => $request->request_id,
            'runner_account_id' => $runner->id,
            'amount' => '18.50',
            'currency' => 'MYR',
            'message' => 'Saya boleh melaksanakan tugasan ini.',
            'status' => MarketplaceQuotation::STATUS_SUBMITTED,
        ]);
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
