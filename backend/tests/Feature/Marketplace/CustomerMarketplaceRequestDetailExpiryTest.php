<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMarketplaceRequestDetailExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_request_detail_expires_due_open_request(): void
    {
        $customer = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Request Detail Expiry Customer',
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

        $requestId = (string) Str::uuid();

        MarketplaceRequest::create([
            'request_id' => $requestId,
            'customer_account_id' => $customer->id,
            'source_type' => MarketplaceRequest::SOURCE_MARKETPLACE,
            'task_class' => MarketplaceRequest::TASK_CLASS_NON_DELIVERY,
            'description' => 'Permintaan yang sudah tamat tempoh.',
            'task_location_snapshot' => [
                'address' => 'Tuaran',
            ],
            'status' => MarketplaceRequest::STATUS_OPEN,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->getJson(
            '/web/customer/requests/'.$requestId
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.request.request_id',
                $requestId
            )
            ->assertJsonPath(
                'data.request.status',
                MarketplaceRequest::STATUS_EXPIRED
            );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'customer_account_id' => $customer->id,
            'status' => MarketplaceRequest::STATUS_EXPIRED,
        ]);
    }

    public function test_customer_request_detail_still_requires_web_session(): void
    {
        $this->getJson(
            '/web/customer/requests/'.(string) Str::uuid()
        )->assertUnauthorized();
    }
}
