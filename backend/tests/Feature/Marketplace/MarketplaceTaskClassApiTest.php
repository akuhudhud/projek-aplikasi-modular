<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceTaskClassApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pickup_delivery_request_stores_only_pickup_and_delivery_snapshots(): void
    {
        $customer = $this->makeAccount();
        $token = $this->createSessionToken($customer);

        $response = $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'PICKUP_DELIVERY',
                'description' => 'Ambil barang dan hantar ke alamat pelanggan.',
                'pickup_location_snapshot' => [
                    'label' => 'Lokasi pengambilan',
                    'address' => 'Lokasi A',
                ],
                'delivery_location_snapshot' => [
                    'label' => 'Lokasi penghantaran',
                    'address' => 'Lokasi B',
                ],
                'task_location_snapshot' => [
                    'label' => 'Lokasi tidak sepatutnya digunakan',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.request.task_class',
                'PICKUP_DELIVERY'
            )
            ->assertJsonPath(
                'data.request.pickup_location_snapshot.address',
                'Lokasi A'
            )
            ->assertJsonPath(
                'data.request.delivery_location_snapshot.address',
                'Lokasi B'
            )
            ->assertJsonPath(
                'data.request.task_location_snapshot',
                null
            );

        $requestId = $response->json('data.request.request_id');

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'task_class' => 'PICKUP_DELIVERY',
        ]);

        $this->assertDatabaseCount('marketplace_requests', 1);
    }

    public function test_non_delivery_request_stores_only_task_location_snapshot(): void
    {
        $customer = $this->makeAccount();
        $token = $this->createSessionToken($customer);

        $response = $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'NON_DELIVERY',
                'description' => 'Bantu urus tugasan di lokasi pelanggan.',
                'task_location_snapshot' => [
                    'label' => 'Lokasi tugasan',
                    'address' => 'Lokasi C',
                ],
                'pickup_location_snapshot' => [
                    'label' => 'Tidak digunakan',
                ],
                'delivery_location_snapshot' => [
                    'label' => 'Tidak digunakan',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.request.task_class',
                'NON_DELIVERY'
            )
            ->assertJsonPath(
                'data.request.task_location_snapshot.address',
                'Lokasi C'
            )
            ->assertJsonPath(
                'data.request.pickup_location_snapshot',
                null
            )
            ->assertJsonPath(
                'data.request.delivery_location_snapshot',
                null
            );

        $requestId = $response->json('data.request.request_id');

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'task_class' => 'NON_DELIVERY',
        ]);

        $this->assertDatabaseCount('marketplace_requests', 1);
    }

    public function test_create_request_rejects_unsupported_task_class(): void
    {
        $customer = $this->makeAccount();
        $token = $this->createSessionToken($customer);

        $this->withToken($token)
            ->postJson('/api/marketplace/requests', [
                'task_class' => 'GENERAL_SERVICE',
                'description' => 'Unsupported task class.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'task_class',
            ]);

        $this->assertDatabaseCount('marketplace_requests', 0);
        $this->assertDatabaseCount('marketplace_quotations', 0);
        $this->assertDatabaseCount('core_tasks', 0);
    }

    private function makeAccount(): Account
    {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Marketplace Task Class API Test',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => Account::RUNNER_STATUS_NOT_RUNNER,
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
