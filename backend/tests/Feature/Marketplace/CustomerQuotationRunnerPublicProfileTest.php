<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\RunnerPublicProfile;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerQuotationRunnerPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $profileName,
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'profile_name' => $profileName,
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

    public function test_customer_can_view_published_runner_public_profile_in_quotation(): void
    {
        $customer = $this->createAccount('Customer Profile Test');

        $runner = $this->createAccount(
            'Runner Tuaran',
            Account::RUNNER_STATUS_APPROVED
        );

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => 'runner-tuaran',
            'bio' => 'Servis penghantaran sekitar Tuaran.',
            'base_location' => 'Tuaran',
            'service_area' => 'Tuaran dan kawasan berhampiran',
            'services' => [
                'Penghantaran parcel',
                'Penghantaran dokumen',
            ],
            'is_published' => true,
            'views_count' => 0,
        ]);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $customerToken,
        ]);

        $requestResponse = $this->postJson(
            '/web/customer/requests',
            [
                'task_class' => 'NON_DELIVERY',
                'description' => 'Bantu beli barang.',
                'task_location_snapshot' => [
                    'address' => 'Pekan Tuaran',
                ],
                'expires_at' => now()->addHours(3)->toDateTimeString(),
            ]
        );

        $requestResponse->assertCreated();

        $requestId = $requestResponse->json(
            'data.request.request_id'
        );

        $quotationResponse = $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '18.50',
                    'currency' => 'MYR',
                    'message' => 'Saya boleh siapkan tugasan ini.',
                ]
            );

        $quotationResponse->assertCreated();

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $customerToken,
        ]);

        $response = $this->getJson(
            '/web/customer/requests/'.$requestId
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath(
                'data.quotations.0.runner_account_id'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.public_slug',
                'runner-tuaran'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.profile_name',
                'Runner Tuaran'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.base_location',
                'Tuaran'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.service_area',
                'Tuaran dan kawasan berhampiran'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.bio',
                'Servis penghantaran sekitar Tuaran.'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.services.0',
                'Penghantaran parcel'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.rating_count',
                0
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.rating_average',
                null
            );
    }

    public function test_customer_does_not_receive_unpublished_runner_profile_details(): void
    {
        $customer = $this->createAccount('Customer Profile Privacy Test');

        $runner = $this->createAccount(
            'Runner Private',
            Account::RUNNER_STATUS_APPROVED
        );

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => 'runner-private',
            'base_location' => 'Private Base',
            'service_area' => 'Private Area',
            'services' => ['Private Service'],
            'is_published' => false,
            'views_count' => 0,
        ]);

        $customerToken = $this->createSessionToken($customer);
        $runnerToken = $this->createSessionToken($runner);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $customerToken,
        ]);

        $requestResponse = $this->postJson(
            '/web/customer/requests',
            [
                'task_class' => 'NON_DELIVERY',
                'description' => 'Permintaan ujian profil tersembunyi.',
                'task_location_snapshot' => [
                    'address' => 'Tuaran',
                ],
                'expires_at' => now()->addHours(3)->toDateTimeString(),
            ]
        );

        $requestResponse->assertCreated();

        $requestId = $requestResponse->json(
            'data.request.request_id'
        );

        $this->withToken($runnerToken)
            ->postJson(
                '/api/marketplace/requests/'.$requestId.'/quotations',
                [
                    'amount' => '20.00',
                    'currency' => 'MYR',
                    'message' => 'Saya boleh membantu.',
                ]
            )
            ->assertCreated();

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $customerToken,
        ]);

        $response = $this->getJson(
            '/web/customer/requests/'.$requestId
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath(
                'data.quotations.0.runner_account_id'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile',
                null
            );

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $runner->id,
            'public_slug' => 'runner-private',
            'views_count' => 0,
        ]);
    }
}
