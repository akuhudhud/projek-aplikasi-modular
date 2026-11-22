<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerApplicationPaginationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $name,
        string $email,
        string $role = 'USER',
        string $runnerStatus = Account::RUNNER_STATUS_PENDING
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => $name,
            'phone' => '60'.random_int(100000000, 999999999),
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        return $account->fresh();
    }

    private function createPendingLicense(Account $account): void
    {
        RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'license_number' => 'D123456789012',
            'license_type' => 'CDL',
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
            'submitted_at' => now(),
            'disk' => 'local',
            'document_path' => 'runner-licenses/'.$account->id.'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 204800,
        ]);
    }

    private function authenticate(Account $account): string
    {
        $token = 'runner-application-pagination-'.Str::random(24);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $token;
    }

    private function grantPermission(
        Account $account,
        string $permission
    ): void {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => $permission,
        ]);
    }

    public function test_pending_applications_use_server_side_pagination(): void
    {
        $admin = $this->createAccount(
            'Pagination Admin',
            'runner-pagination-admin@example.com',
            'SUPER_ADMIN'
        );

        $this->grantPermission(
            $admin,
            'runner_applications.view'
        );

        for ($index = 1; $index <= 12; $index++) {
            $applicant = $this->createAccount(
                'Pagination Applicant '.$index,
                'runner-pagination-'.$index.'@example.com'
            );

            $this->createPendingLicense($applicant);
        }

        $token = $this->authenticate($admin);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson(
            '/api/admin/runner-applications?per_page=5&page=2'
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.pagination.total', 12)
            ->assertJsonPath('data.pagination.last_page', 3)
            ->assertJsonPath('data.pagination.from', 6)
            ->assertJsonPath('data.pagination.to', 10)
            ->assertJsonCount(5, 'data.applications');
    }

    public function test_pending_applications_reject_invalid_pagination_parameters(): void
    {
        $admin = $this->createAccount(
            'Validation Admin',
            'runner-pagination-validation@example.com',
            'SUPER_ADMIN'
        );

        $this->grantPermission(
            $admin,
            'runner_applications.view'
        );

        $token = $this->authenticate($admin);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson(
            '/api/admin/runner-applications?per_page=101'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_pending_applications_require_view_permission(): void
    {
        $admin = $this->createAccount(
            'Restricted Admin',
            'runner-pagination-restricted@example.com',
            'SUPER_ADMIN'
        );

        $token = $this->authenticate($admin);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/runner-applications')
            ->assertForbidden();
    }

    public function test_pending_applications_require_authentication(): void
    {
        $this->getJson('/api/admin/runner-applications')
            ->assertUnauthorized();
    }
}
