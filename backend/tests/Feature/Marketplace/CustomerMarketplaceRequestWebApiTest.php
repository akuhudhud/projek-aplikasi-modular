<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\MarketplaceRequest;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMarketplaceRequestWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Customer Open Request Test',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticateCustomer(Account $account): void
    {
        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    private function createNonDeliveryRequest(): void
    {
        $this->postJson('/web/customer/requests', [
            'task_class' => 'NON_DELIVERY',
            'description' => 'Bantu beli barang di kedai.',
            'task_location_snapshot' => [
                'address' => 'Kedai yang ditetapkan',
                'contact_name' => 'Pemilik kedai',
                'phone' => '0123456789',
            ],
            'expires_at' => now()->addHours(3)->toDateTimeString(),
        ])->assertCreated();
    }

    public function test_customer_can_create_pickup_delivery_request(): void
    {
        $customer = $this->createAccount();
        $this->authenticateCustomer($customer);

        $response = $this->postJson('/web/customer/requests', [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Hantar barang ke alamat penerima.',
            'pickup_location_snapshot' => [
                'address' => 'Lokasi pickup',
                'contact_name' => 'Pengirim',
                'phone' => '0123456789',
            ],
            'delivery_location_snapshot' => [
                'address' => 'Lokasi delivery',
                'contact_name' => 'Penerima',
                'phone' => '0198765432',
            ],
            'expires_at' => now()->addHours(2)->toDateTimeString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.request.task_class',
                MarketplaceRequest::TASK_CLASS_PICKUP_DELIVERY
            )
            ->assertJsonPath(
                'data.request.pickup_location_snapshot.address',
                'Lokasi pickup'
            )
            ->assertJsonPath(
                'data.request.delivery_location_snapshot.address',
                'Lokasi delivery'
            )
            ->assertJsonPath(
                'data.request.customer_account_id',
                $customer->id
            );

        $this->assertDatabaseCount('marketplace_requests', 1);
        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_customer_can_create_non_delivery_request(): void
    {
        $customer = $this->createAccount();
        $this->authenticateCustomer($customer);

        $response = $this->postJson('/web/customer/requests', [
            'task_class' => 'NON_DELIVERY',
            'description' => 'Bantu beli barang di kedai.',
            'task_location_snapshot' => [
                'address' => 'Kedai yang ditetapkan',
                'contact_name' => 'Pemilik kedai',
                'phone' => '0123456789',
            ],
            'expires_at' => now()->addHours(3)->toDateTimeString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.request.task_class',
                MarketplaceRequest::TASK_CLASS_NON_DELIVERY
            )
            ->assertJsonPath(
                'data.request.task_location_snapshot.address',
                'Kedai yang ditetapkan'
            )
            ->assertJsonPath(
                'data.request.customer_account_id',
                $customer->id
            );

        $this->assertDatabaseCount('marketplace_requests', 1);
        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_pickup_delivery_request_requires_both_locations(): void
    {
        $customer = $this->createAccount();
        $this->authenticateCustomer($customer);

        $this->postJson('/web/customer/requests', [
            'task_class' => 'PICKUP_DELIVERY',
            'description' => 'Permintaan tanpa lokasi lengkap.',
            'pickup_location_snapshot' => [
                'address' => 'Lokasi pickup',
            ],
            'expires_at' => now()->addHours(2)->toDateTimeString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'delivery_location_snapshot',
            ]);

        $this->assertDatabaseCount('marketplace_requests', 0);
        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_non_delivery_request_requires_task_location(): void
    {
        $customer = $this->createAccount();
        $this->authenticateCustomer($customer);

        $this->postJson('/web/customer/requests', [
            'task_class' => 'NON_DELIVERY',
            'description' => 'Permintaan tanpa lokasi tugasan.',
            'expires_at' => now()->addHours(2)->toDateTimeString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'task_location_snapshot',
            ]);

        $this->assertDatabaseCount('marketplace_requests', 0);
        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_customer_request_rejects_unsupported_task_class(): void
    {
        $customer = $this->createAccount();
        $this->authenticateCustomer($customer);

        $this->postJson('/web/customer/requests', [
            'task_class' => 'OTHER',
            'description' => 'Task class tidak dibenarkan.',
            'task_location_snapshot' => [
                'address' => 'Lokasi tugasan',
            ],
            'expires_at' => now()->addHours(2)->toDateTimeString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'task_class',
            ]);

        $this->assertDatabaseCount('marketplace_requests', 0);
        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_customer_request_endpoint_requires_web_session(): void
    {
        $this->postJson('/web/customer/requests', [
            'task_class' => 'NON_DELIVERY',
            'description' => 'Unauthenticated request.',
            'task_location_snapshot' => [
                'address' => 'Lokasi tugasan',
            ],
            'expires_at' => now()->addHours(2)->toDateTimeString(),
        ])->assertUnauthorized();

        $this->assertDatabaseCount('marketplace_requests', 0);
    }

    public function test_customer_can_only_view_own_marketplace_requests(): void
    {
        $firstCustomer = $this->createAccount();
        $this->authenticateCustomer($firstCustomer);
        $this->createNonDeliveryRequest();

        $secondCustomer = $this->createAccount();
        $this->authenticateCustomer($secondCustomer);
        $this->createNonDeliveryRequest();

        $this->authenticateCustomer($firstCustomer);

        $response = $this->getJson('/web/customer/requests');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 25)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.pagination.last_page', 1)
            ->assertJsonCount(1, 'data.requests')
            ->assertJsonPath(
                'data.requests.0.description',
                'Bantu beli barang di kedai.'
            )
            ->assertJsonMissingPath('data.requests.0.customer_account_id');

        $this->assertDatabaseCount('marketplace_requests', 2);
    }

    public function test_customer_request_listing_requires_web_session(): void
    {
        $this->getJson('/web/customer/requests')
            ->assertUnauthorized();
    }
}
