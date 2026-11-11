<?php

namespace Tests\Feature\Marketplace;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Models\RunnerPublicProfile;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerMarketplaceQuotationWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'profile_name' => 'Marketplace Quotation Web Test',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => $runnerStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }

    private function createPublicProfile(Account $runner): RunnerPublicProfile
    {
        return RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => 'runner-web-test',
            'bio' => 'Runner sekitar Tuaran.',
            'base_location' => 'Tuaran',
            'service_area' => 'Tuaran dan kawasan berhampiran',
            'services' => ['Penghantaran parcel'],
            'is_published' => true,
            'views_count' => 0,
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

    private function authenticateCustomer(Account $account): void
    {
        $token = $this->createSessionToken($account);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    private function createNonDeliveryRequest(): string
    {
        $response = $this->postJson('/web/customer/requests', [
            'task_class' => MarketplaceRequest::TASK_CLASS_NON_DELIVERY,
            'description' => 'Bantu beli barang di kedai.',
            'task_location_snapshot' => [
                'address' => 'Kedai yang ditetapkan',
                'contact_name' => 'Pemilik kedai',
                'phone' => '0123456789',
            ],
            'expires_at' => now()->addHours(3)->toDateTimeString(),
        ]);

        $response->assertCreated();

        return (string) $response->json('data.request.request_id');
    }

    private function createRunnerQuotation(
        Account $runner,
        string $requestId
    ): string {
        $token = $this->createSessionToken($runner);

        $response = $this->withToken($token)->postJson(
            '/api/marketplace/requests/'.$requestId.'/quotations',
            [
                'amount' => '18.50',
                'currency' => 'MYR',
                'message' => 'Saya boleh siapkan tugasan ini.',
            ]
        );

        $response->assertCreated()
            ->assertJsonPath(
                'data.quotation.status',
                MarketplaceQuotation::STATUS_SUBMITTED
            );

        return (string) $response->json(
            'data.quotation.quotation_id'
        );
    }

    public function test_customer_can_view_quotations_for_own_request(): void
    {
        $customer = $this->createAccount();
        $runner = $this->createAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $runner->forceFill([
            'profile_name' => 'Nama Paparan Runner',
        ])->save();

        $this->createPublicProfile($runner);

        $this->authenticateCustomer($customer);

        $requestId = $this->createNonDeliveryRequest();

        $quotationId = $this->createRunnerQuotation(
            $runner,
            $requestId
        );

        $this->authenticateCustomer($customer);

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
                MarketplaceRequest::STATUS_OPEN
            )
            ->assertJsonCount(1, 'data.quotations')
            ->assertJsonPath(
                'data.quotations.0.quotation_id',
                $quotationId
            )
            ->assertJsonMissingPath(
                'data.quotations.0.runner_account_id'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.public_slug',
                'runner-web-test'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.profile_name',
                'Nama Paparan Runner'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.base_location',
                'Tuaran'
            )
            ->assertJsonPath(
                'data.quotations.0.runner_profile.rating_count',
                0
            )
            ->assertJsonPath(
                'data.quotations.0.amount',
                '18.50'
            )
            ->assertJsonPath(
                'data.quotations.0.currency',
                'MYR'
            )
            ->assertJsonPath(
                'data.quotations.0.status',
                MarketplaceQuotation::STATUS_SUBMITTED
            );
    }

    public function test_customer_cannot_view_another_customers_request_quotations(): void
    {
        $owner = $this->createAccount();

        $this->authenticateCustomer($owner);

        $requestId = $this->createNonDeliveryRequest();

        $otherCustomer = $this->createAccount();

        $this->authenticateCustomer($otherCustomer);

        $this->getJson(
            '/web/customer/requests/'.$requestId
        )
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'customer_account_id' => $owner->id,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);
    }

    public function test_customer_can_award_quotation_and_create_core_task(): void
    {
        $customer = $this->createAccount();
        $runner = $this->createAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $this->authenticateCustomer($customer);

        $requestId = $this->createNonDeliveryRequest();

        $quotationId = $this->createRunnerQuotation(
            $runner,
            $requestId
        );

        $this->authenticateCustomer($customer);

        $response = $this->postJson(
            '/web/customer/requests/'.$requestId.'/award',
            [
                'quotation_id' => $quotationId,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.request.request_id',
                $requestId
            )
            ->assertJsonPath(
                'data.request.status',
                MarketplaceRequest::STATUS_AWARDED
            )
            ->assertJsonPath(
                'data.request.winning_quotation_id',
                $quotationId
            )
            ->assertJsonMissingPath(
                'data.task.assigned_account_id'
            )
            ->assertJsonPath(
                'data.task.generic_status',
                'ASSIGNED'
            );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'customer_account_id' => $customer->id,
            'status' => MarketplaceRequest::STATUS_AWARDED,
            'winning_quotation_id' => $quotationId,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'request_id' => $requestId,
            'runner_account_id' => $runner->id,
            'status' => MarketplaceQuotation::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('core_tasks', [
            'module_key' => 'Marketplace',
            'task_type' => 'MARKETPLACE_SERVICE_REQUEST',
            'source_type' => 'marketplace_request',
            'source_id' => $requestId,
            'assigned_account_id' => $runner->id,
        ]);

        $this->assertDatabaseCount('core_tasks', 1);
    }

    public function test_customer_cannot_award_another_customers_quotation(): void
    {
        $owner = $this->createAccount();
        $otherCustomer = $this->createAccount();

        $runner = $this->createAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $this->authenticateCustomer($owner);

        $requestId = $this->createNonDeliveryRequest();

        $quotationId = $this->createRunnerQuotation(
            $runner,
            $requestId
        );

        $this->authenticateCustomer($otherCustomer);

        $response = $this->postJson(
            '/web/customer/requests/'.$requestId.'/award',
            [
                'quotation_id' => $quotationId,
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Only the request owner may select a quotation.'
            );

        $this->assertDatabaseHas('marketplace_requests', [
            'request_id' => $requestId,
            'customer_account_id' => $owner->id,
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('marketplace_quotations', [
            'quotation_id' => $quotationId,
            'status' => MarketplaceQuotation::STATUS_SUBMITTED,
        ]);

        $this->assertDatabaseCount('core_tasks', 0);
    }

    public function test_quotation_detail_endpoint_requires_web_session(): void
    {
        $this->getJson(
            '/web/customer/requests/'.(string) Str::uuid()
        )->assertUnauthorized();
    }

    public function test_award_quotation_endpoint_requires_web_session(): void
    {
        $this->postJson(
            '/web/customer/requests/'.(string) Str::uuid().'/award',
            [
                'quotation_id' => (string) Str::uuid(),
            ]
        )->assertUnauthorized();

        $this->assertDatabaseCount('core_tasks', 0);
    }
}
