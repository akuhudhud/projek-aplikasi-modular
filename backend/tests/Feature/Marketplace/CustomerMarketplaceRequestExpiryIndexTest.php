<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMarketplaceRequestExpiryIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_listing_expires_due_open_requests(): void
    {
        $customer = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Expiry Index Customer',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $customer->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);

        $expiredRequestId = (string) Str::uuid();

        MarketplaceRequest::create([
            'request_id' => $expiredRequestId,
            'customer_account_id' => $customer->id,
            'source_type' => MarketplaceRequest::SOURCE_MARKETPLACE,
            'task_class' => MarketplaceRequest::TASK_CLASS_NON_DELIVERY,
            'description' => 'Permintaan yang sudah luput.',
            'task_location_snapshot' => [
                'address' => 'Tuaran',
            ],
            'status' => MarketplaceRequest::STATUS_OPEN,
            'expires_at' => now()->subMinute(),
        ]);

        $activeRequestId = (string) Str::uuid();

        MarketplaceRequest::create([
            'request_id' => $activeRequestId,
            'customer_account_id' => $customer->id,
            'source_type' => MarketplaceRequest::SOURCE_MARKETPLACE,
            'task_class' => MarketplaceRequest::TASK_CLASS_NON_DELIVERY,
            'description' => 'Permintaan yang masih aktif.',
            'task_location_snapshot' => [
                'address' => 'Tamparuli',
            ],
            'status' => MarketplaceRequest::STATUS_OPEN,
            'expires_at' => now()->addHours(2),
        ]);

        $response = $this->getJson('/web/customer/requests');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 2);

        $requests = collect(
            $response->json('data.requests')
        )->keyBy('request_id');

        $this->assertSame(
            MarketplaceRequest::STATUS_EXPIRED,
            $requests->get($expiredRequestId)['status']
        );

        $this->assertSame(
            MarketplaceRequest::STATUS_OPEN,
            $requests->get($activeRequestId)['status']
        );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $expiredRequestId,
            'status' => MarketplaceRequest::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $activeRequestId,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);
    }

    public function test_request_listing_still_requires_web_session(): void
    {
        $this->getJson('/web/customer/requests')
            ->assertUnauthorized();
    }
}
