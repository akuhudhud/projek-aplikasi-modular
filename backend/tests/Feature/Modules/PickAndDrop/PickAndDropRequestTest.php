<?php

namespace Tests\Feature\Modules\PickAndDrop;

use App\Models\Account;
use App\Models\Session;
use App\Services\Session\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\PickAndDrop\Models\PickAndDropRequest;
use Modules\PickAndDrop\Models\PickAndDropTask;
use RuntimeException;
use Tests\TestCase;

class PickAndDropRequestTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Customer',
            'phone' => '60123456789',
            'email' => 'customer@example.test',
            'password' => 'TestPassword123',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $this->token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $this->account->id,
            'token_hash' => hash('sha256', $this->token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $this->mock(SessionService::class)
            ->shouldReceive('touch')
            ->zeroOrMoreTimes();
    }

    public function test_customer_can_create_request_and_task(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/pick-and-drop/requests', [
                'category' => 'BARANG',
                'item_type' => 'PARCEL',
                'item_description' => 'Satu kotak parcel',
                'pickup_location' => [
                    'label' => 'Rumah Pengirim',
                    'address' => 'Tuaran, Sabah',
                ],
                'pickup_contact_name' => 'Pengirim',
                'pickup_contact_phone' => '60111111111',
                'delivery_location' => [
                    'label' => 'Rumah Penerima',
                    'address' => 'Kota Kinabalu, Sabah',
                ],
                'recipient_name' => 'Penerima',
                'recipient_phone' => '60222222222',
                'additional_instructions' => 'Hubungi sebelum tiba.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', 'SUBMITTED')
            ->assertJsonPath('data.task.status', 'AVAILABLE');

        $this->assertDatabaseCount('pick_and_drop_requests', 1);
        $this->assertDatabaseCount('pick_and_drop_tasks', 1);

        $request = PickAndDropRequest::query()->firstOrFail();

        $this->assertSame(
            $this->account->id,
            $request->customer_account_id
        );

        $this->assertSame(
            'Tuaran, Sabah',
            $request->pickup_location_snapshot['address']
        );

        $this->assertSame(
            'Kota Kinabalu, Sabah',
            $request->delivery_location_snapshot['address']
        );

        $this->assertDatabaseHas('pick_and_drop_tasks', [
            'request_id' => $request->id,
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_document_request_does_not_require_item_type(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/pick-and-drop/requests', [
                'category' => 'DOKUMEN',
                'item_description' => 'Dokumen perjanjian',
                'pickup_location' => [
                    'label' => 'Pejabat Pengirim',
                    'address' => 'Tuaran, Sabah',
                ],
                'pickup_contact_name' => 'Pengirim',
                'pickup_contact_phone' => '60111111111',
                'delivery_location' => [
                    'label' => 'Pejabat Penerima',
                    'address' => 'Kota Kinabalu, Sabah',
                ],
                'recipient_name' => 'Penerima',
                'recipient_phone' => '60222222222',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.request.category', 'DOKUMEN')
            ->assertJsonPath('data.request.item_type', null);

        $this->assertDatabaseCount('pick_and_drop_requests', 1);
        $this->assertDatabaseCount('pick_and_drop_tasks', 1);
    }

    public function test_request_cannot_be_created_without_authentication(): void
    {
        $response = $this->postJson(
            '/api/pick-and-drop/requests',
            []
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount('pick_and_drop_requests', 0);
        $this->assertDatabaseCount('pick_and_drop_tasks', 0);
    }

    public function test_invalid_request_does_not_create_records(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/pick-and-drop/requests', [
                'category' => 'INVALID',
                'item_description' => 'Barang ujian',
                'pickup_location' => [
                    'label' => 'Lokasi Pickup',
                    'address' => 'Tuaran, Sabah',
                ],
                'pickup_contact_name' => 'Pengirim',
                'pickup_contact_phone' => '60111111111',
                'delivery_location' => [
                    'label' => 'Lokasi Delivery',
                    'address' => 'Kota Kinabalu, Sabah',
                ],
                'recipient_name' => 'Penerima',
                'recipient_phone' => '60222222222',
            ]);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('pick_and_drop_requests', 0);
        $this->assertDatabaseCount('pick_and_drop_tasks', 0);
    }

    public function test_request_creation_is_rolled_back_when_task_creation_fails(): void
    {
        PickAndDropTask::creating(function (): void {
            throw new RuntimeException(
                'Simulated task creation failure.'
            );
        });

        $this->withoutExceptionHandling();

        try {
            $this->withToken($this->token)
                ->postJson('/api/pick-and-drop/requests', [
                    'category' => 'BARANG',
                    'item_type' => 'PARCEL',
                    'item_description' => 'Parcel untuk ujian transaksi',
                    'pickup_location' => [
                        'label' => 'Lokasi Pickup',
                        'address' => 'Tuaran, Sabah',
                    ],
                    'pickup_contact_name' => 'Pengirim',
                    'pickup_contact_phone' => '60111111111',
                    'delivery_location' => [
                        'label' => 'Lokasi Delivery',
                        'address' => 'Kota Kinabalu, Sabah',
                    ],
                    'recipient_name' => 'Penerima',
                    'recipient_phone' => '60222222222',
                ]);

            $this->fail(
                'Task creation failure should abort the transaction.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated task creation failure.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('pick_and_drop_requests', 0);
        $this->assertDatabaseCount('pick_and_drop_tasks', 0);
    }
}
