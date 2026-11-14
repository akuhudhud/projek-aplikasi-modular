<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use App\Notifications\MarketplaceEventNotification;
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

        $this->assertSame(1, $customer->notifications()
            ->where('type', MarketplaceEventNotification::class)
            ->count());

        $this->assertSame(1, $runner->notifications()
            ->where('type', MarketplaceEventNotification::class)
            ->count());
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
