<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\Session;
use App\Services\AdminPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class MeAdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSession(
        string $role,
        string $token
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Test Admin',
            'email' => strtolower($role).'@example.com',
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $account;
    }

    public function test_root_super_admin_receives_full_permission_catalog(): void
    {
        $this->createAccountWithSession(
            'ROOT_SUPER_ADMIN',
            'root-permission-token'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer root-permission-token'
        )->getJson('/api/me');

        $permissions = app(AdminPermissionService::class)
            ->permissionKeys();

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.account.permissions',
                $permissions
            );

        $this->assertNotContains(
            'runner_license_verifications.review',
            $permissions
        );
    }

    public function test_legacy_runner_license_permission_is_not_in_catalog(): void
    {
        $service = app(AdminPermissionService::class);

        $this->assertNotContains(
            'runner_license_verifications.review',
            $service->permissionKeys()
        );

        $this->assertNotContains(
            'runner_license_verifications.review',
            array_column($service->catalog(), 'key')
        );
    }

    public function test_legacy_runner_license_permission_cannot_be_assigned(): void
    {
        $account = $this->createAccountWithSession(
            'SUPER_ADMIN',
            'legacy-permission-token'
        );

        $this->expectException(InvalidArgumentException::class);

        app(AdminPermissionService::class)->replace(
            $account,
            ['runner_license_verifications.review']
        );
    }

    public function test_super_admin_receives_only_assigned_permissions(): void
    {
        $account = $this->createAccountWithSession(
            'SUPER_ADMIN',
            'super-admin-permission-token'
        );

        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'accounts.view',
        ]);

        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'audit_logs.view',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer super-admin-permission-token'
        )->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.account.permissions',
                [
                    'accounts.view',
                    'audit_logs.view',
                ]
            );
    }

    public function test_regular_user_receives_empty_permission_list(): void
    {
        $this->createAccountWithSession(
            'USER',
            'regular-user-permission-token'
        );

        $this->withHeader(
            'Authorization',
            'Bearer regular-user-permission-token'
        )->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath(
                'data.account.permissions',
                []
            );
    }
}
