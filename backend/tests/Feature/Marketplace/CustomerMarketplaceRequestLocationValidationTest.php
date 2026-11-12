<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMarketplaceRequestLocationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_delivery_requires_both_addresses(): void
    {
        $this->authenticateCustomer();

        $response = $this->postJson('/web/customer/requests', [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Ambil barang dan hantar kepada pelanggan.',
            'pickup_location_snapshot' => [
                'address' => ' ',
            ],
            'delivery_location_snapshot' => [],
            'expires_at' => now()->addHours(2)->toISOString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pickup_location_snapshot.address',
                'delivery_location_snapshot.address',
            ]);

        $this->assertDatabaseCount('marketplace_requests', 0);
    }

    public function test_non_delivery_requires_task_address(): void
    {
        $this->authenticateCustomer();

        $response = $this->postJson('/web/customer/requests', [
            'task_class' => 'NON_DELIVERY',
            'description' => 'Bantu urus satu tugasan.',
            'task_location_snapshot' => [],
            'expires_at' => now()->addHours(2)->toISOString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'task_location_snapshot.address',
            ]);

        $this->assertDatabaseCount('marketplace_requests', 0);
    }

    public function test_non_delivery_rejects_blank_task_address(): void
    {
        $this->authenticateCustomer();

        $response = $this->postJson('/web/customer/requests', [
            'task_class' => 'NON_DELIVERY',
            'description' => 'Bantu urus satu tugasan.',
            'task_location_snapshot' => [
                'address' => '',
            ],
            'expires_at' => now()->addHours(2)->toISOString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'task_location_snapshot.address',
            ]);

        $this->assertDatabaseCount('marketplace_requests', 0);
    }

    private function authenticateCustomer(): Account
    {
        $customer = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Location Validation Customer',
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

        return $customer;
    }
}
