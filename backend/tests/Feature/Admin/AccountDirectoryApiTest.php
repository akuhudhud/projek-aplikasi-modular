<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountDirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $name,
        string $email,
        string $role = 'USER',
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => $name,
            'phone' => '60'.random_int(100000000, 999999999),
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function authenticate(Account $account): string
    {
        $token = 'directory-token-'.Str::random(24);

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

    public function test_directory_requires_authentication(): void
    {
        $this->getJson('/api/admin/account-directory')
            ->assertUnauthorized();
    }

    public function test_root_can_view_all_account_roles(): void
    {
        $root = $this->createAccount(
            'Root Admin',
            'directory-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $this->createAccount(
            'Super Admin',
            'directory-admin@example.com',
            'SUPER_ADMIN'
        );

        $this->createAccount(
            'Regular User',
            'directory-user@example.com'
        );

        $token = $this->authenticate($root);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/account-directory')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_super_admin_can_only_view_user_accounts(): void
    {
        $admin = $this->createAccount(
            'Directory Admin',
            'directory-super@example.com',
            'SUPER_ADMIN'
        );

        $this->grantPermission($admin, 'accounts.view');

        $this->createAccount(
            'Another Admin',
            'directory-another-admin@example.com',
            'SUPER_ADMIN'
        );

        $user = $this->createAccount(
            'Directory User',
            'directory-regular@example.com'
        );

        $token = $this->authenticate($admin);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/account-directory');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment([
                'id' => $user->id,
                'profile_name' => 'Directory User',
            ])
            ->assertJsonMissing([
                'id' => $admin->id,
            ]);
    }

    public function test_directory_search_filters_account_records(): void
    {
        $root = $this->createAccount(
            'Root Admin',
            'directory-search-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $matching = $this->createAccount(
            'Nazim Example',
            'nazim-directory@example.com'
        );

        $this->createAccount(
            'Other Person',
            'other-directory@example.com'
        );

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/account-directory?search=Nazim');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment([
                'id' => $matching->id,
                'profile_name' => 'Nazim Example',
            ]);
    }

    public function test_directory_filters_by_status_and_role(): void
    {
        $root = $this->createAccount(
            'Root Admin',
            'directory-filter-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $matching = $this->createAccount(
            'Suspended User',
            'directory-suspended@example.com',
            'USER',
            'SUSPENDED'
        );

        $this->createAccount(
            'Active User',
            'directory-active@example.com'
        );

        $this->createAccount(
            'Suspended Admin',
            'directory-suspended-admin@example.com',
            'SUPER_ADMIN',
            'SUSPENDED'
        );

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson(
            '/api/admin/account-directory?status=SUSPENDED&role=USER'
        );

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment([
                'id' => $matching->id,
                'status' => 'SUSPENDED',
                'role' => 'USER',
            ]);
    }

    public function test_directory_uses_server_side_pagination(): void
    {
        $root = $this->createAccount(
            'Root Admin',
            'directory-pagination-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        for ($index = 1; $index <= 12; $index++) {
            $this->createAccount(
                'Pagination User '.$index,
                'pagination-user-'.$index.'@example.com'
            );
        }

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/account-directory?per_page=5&page=2');

        $response->assertOk()
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.pagination.total', 13)
            ->assertJsonCount(5, 'data.accounts');
    }

    public function test_verified_name_is_only_returned_for_verified_identity(): void
    {
        $root = $this->createAccount(
            'Root Admin',
            'directory-identity-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $verified = $this->createAccount(
            'Verified Display',
            'directory-verified@example.com'
        );

        $pending = $this->createAccount(
            'Pending Display',
            'directory-pending@example.com'
        );

        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $verified->id,
            'verified_name' => 'Verified Real Name',
            'mykad_number' => '900101145678',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $pending->id,
            'verified_name' => 'Pending Real Name',
            'mykad_number' => '900101145679',
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/account-directory');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $verified->id,
                'verified_name' => 'Verified Real Name',
            ])
            ->assertJsonFragment([
                'id' => $pending->id,
                'verified_name' => null,
            ])
            ->assertJsonMissing([
                'verified_name' => 'Pending Real Name',
            ])
            ->assertJsonMissing([
                'mykad_number' => '900101145678',
            ]);
    }

    public function test_account_detail_returns_safe_fields_only(): void
    {
        $root = $this->createAccount(
            'Root Admin',
            'directory-detail-root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $user = $this->createAccount(
            'Detail User',
            'directory-detail-user@example.com'
        );

        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $user->id,
            'verified_name' => 'Verified Detail Name',
            'mykad_number' => '900101145678',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $token = $this->authenticate($root);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson(
            '/api/admin/account-directory/'.$user->id
        );

        $response->assertOk()
            ->assertJsonPath(
                'data.account.profile_name',
                'Detail User'
            )
            ->assertJsonPath(
                'data.account.verified_name',
                'Verified Detail Name'
            )
            ->assertJsonPath(
                'data.account.identity_status',
                IdentityVerification::STATUS_VERIFIED
            )
            ->assertJsonMissing([
                'mykad_number' => '900101145678',
            ])
            ->assertJsonMissingPath('data.account.password');
    }

    public function test_super_admin_cannot_open_another_admin_detail(): void
    {
        $admin = $this->createAccount(
            'Directory Admin',
            'directory-detail-admin@example.com',
            'SUPER_ADMIN'
        );

        $this->grantPermission($admin, 'accounts.view');

        $anotherAdmin = $this->createAccount(
            'Another Admin',
            'directory-detail-another@example.com',
            'SUPER_ADMIN'
        );

        $token = $this->authenticate($admin);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(
                '/api/admin/account-directory/'.$anotherAdmin->id
            )
            ->assertNotFound();
    }

    public function test_user_without_permission_cannot_view_directory(): void
    {
        $user = $this->createAccount(
            'Regular User',
            'directory-no-permission@example.com'
        );

        $token = $this->authenticate($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/account-directory')
            ->assertForbidden();
    }
}
