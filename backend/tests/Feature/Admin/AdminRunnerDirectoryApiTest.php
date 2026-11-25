<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRunnerDirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $name,
        string $email,
        string $role = 'USER'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => $name,
            'phone' => '60'.random_int(100000000, 999999999),
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(Account $account): string
    {
        $token = 'runner-directory-token-'.Str::random(24);

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

    private function makeRunner(
        string $name,
        string $email,
        string $status = Account::RUNNER_STATUS_APPROVED,
        string $role = 'USER'
    ): Account {
        $runner = $this->createAccount($name, $email, $role);

        Account::query()
            ->whereKey($runner->id)
            ->update([
                'runner_status' => $status,
            ]);

        return $runner->fresh();
    }

    private function createCurrentLicense(
        Account $runner
    ): RunnerLicenseVerification {
        return RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'license_number' => '123456789012',
            'license_type' => 'CDL',
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_VERIFIED,
            'is_current' => true,
            'submitted_at' => now(),
            'disk' => 'local',
            'document_path' => 'runner-licenses/'.$runner->id.'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 204800,
        ]);
    }

    public function test_directory_requires_authentication(): void
    {
        $this->getJson('/api/admin/runners')
            ->assertUnauthorized();
    }

    public function test_root_can_view_runner_directory(): void
    {
        $root = $this->createAccount(
            'Root Admin',
            'runner-directory-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $runner = $this->makeRunner(
            'Runner Root Test',
            'runner-root-test@example.com'
        );

        $token = $this->authenticate($root);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/runners')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment([
                'id' => $runner->id,
                'profile_name' => 'Runner Root Test',
                'runner_status' => Account::RUNNER_STATUS_APPROVED,
            ]);
    }

    public function test_super_admin_can_only_view_user_runners(): void
    {
        $admin = $this->createAccount(
            'Directory Super Admin',
            'runner-directory-super@example.com',
            'SUPER_ADMIN'
        );

        $this->grantPermission(
            $admin,
            'runner_applications.view'
        );

        $userRunner = $this->makeRunner(
            'User Runner',
            'runner-directory-user@example.com'
        );

        $this->makeRunner(
            'Super Admin Runner',
            'runner-directory-admin-runner@example.com',
            Account::RUNNER_STATUS_APPROVED,
            'SUPER_ADMIN'
        );

        $token = $this->authenticate($admin);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/runners');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment([
                'id' => $userRunner->id,
                'profile_name' => 'User Runner',
            ]);
    }

    public function test_super_admin_without_permission_cannot_view_directory(): void
    {
        $admin = $this->createAccount(
            'Restricted Admin',
            'runner-directory-restricted@example.com',
            'SUPER_ADMIN'
        );

        $token = $this->authenticate($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/runners')
            ->assertForbidden();
    }

    public function test_regular_user_cannot_view_runner_directory(): void
    {
        $user = $this->createAccount(
            'Regular User',
            'runner-directory-regular@example.com'
        );

        $token = $this->authenticate($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/runners')
            ->assertForbidden();
    }

    public function test_directory_filters_by_runner_status(): void
    {
        $root = $this->createAccount(
            'Filter Root',
            'runner-directory-filter-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $approved = $this->makeRunner(
            'Approved Runner',
            'runner-directory-approved@example.com',
            Account::RUNNER_STATUS_APPROVED
        );

        $this->makeRunner(
            'Suspended Runner',
            'runner-directory-suspended@example.com',
            Account::RUNNER_STATUS_SUSPENDED
        );

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/runners?status=APPROVED');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment([
                'id' => $approved->id,
                'runner_status' => Account::RUNNER_STATUS_APPROVED,
            ]);
    }

    public function test_directory_searches_runner_name(): void
    {
        $root = $this->createAccount(
            'Search Root',
            'runner-directory-search-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $matching = $this->makeRunner(
            'Nazim Runner',
            'nazim-runner-directory@example.com'
        );

        $this->makeRunner(
            'Other Runner',
            'other-runner-directory@example.com'
        );

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/runners?search=Nazim');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment([
                'id' => $matching->id,
                'profile_name' => 'Nazim Runner',
            ]);
    }

    public function test_directory_searches_runner_phone_and_email(): void
    {
        $root = $this->createAccount(
            'Phone Email Search Root',
            'runner-directory-phone-email-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $phoneRunner = $this->makeRunner(
            'Phone Search Runner',
            'phone-search-runner@example.com'
        );

        $emailRunner = $this->makeRunner(
            'Email Search Runner',
            'unique-email-search@example.com'
        );

        Account::query()
            ->whereKey($phoneRunner->id)
            ->update([
                'phone' => '60123456789',
            ]);

        $token = $this->authenticate($root);

        $phoneResponse = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/runners?search=60123456789');

        $phoneResponse->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment([
                'id' => $phoneRunner->id,
                'profile_name' => 'Phone Search Runner',
            ]);

        $emailResponse = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/runners?search=unique-email-search@example.com');

        $emailResponse->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment([
                'id' => $emailRunner->id,
                'profile_name' => 'Email Search Runner',
            ]);
    }

    public function test_directory_uses_server_side_pagination(): void
    {
        $root = $this->createAccount(
            'Pagination Root',
            'runner-directory-pagination-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        for ($index = 1; $index <= 12; $index++) {
            $this->makeRunner(
                'Pagination Runner '.$index,
                'runner-directory-pagination-'.$index.'@example.com'
            );
        }

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/runners?per_page=5&page=2');

        $response->assertOk()
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.pagination.total', 12)
            ->assertJsonCount(5, 'data.runners');
    }

    public function test_directory_returns_current_verified_license_summary_only(): void
    {
        $root = $this->createAccount(
            'License Root',
            'runner-directory-license-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $runner = $this->makeRunner(
            'License Runner',
            'runner-directory-license@example.com'
        );

        $this->createCurrentLicense($runner);

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/runners');

        $response->assertOk()
            ->assertJsonPath(
                'data.runners.0.current_license.license_type',
                'CDL'
            )
            ->assertJsonPath(
                'data.runners.0.current_license.license_class',
                'D'
            )
            ->assertJsonPath(
                'data.runners.0.current_license.status',
                RunnerLicenseVerification::STATUS_VERIFIED
            )
            ->assertJsonMissing([
                'license_number' => '123456789012',
            ])
            ->assertJsonMissing([
                'document_path' => 'runner-licenses/'.$runner->id.'.jpg',
            ]);
    }

    public function test_directory_does_not_return_non_current_or_unverified_license(): void
    {
        $root = $this->createAccount(
            'No License Root',
            'runner-directory-no-license-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $runner = $this->makeRunner(
            'Runner Without Current License',
            'runner-directory-no-current-license@example.com'
        );

        RunnerLicenseVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'license_number' => '999999999999',
            'license_type' => 'CDL',
            'license_class' => 'D',
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
            'submitted_at' => now(),
            'disk' => 'local',
            'document_path' => 'runner-licenses/pending-'.$runner->id.'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 102400,
        ]);

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/runners');

        $response->assertOk()
            ->assertJsonPath(
                'data.runners.0.current_license',
                null
            );
    }
}
