
<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\Session;
use App\Services\AdminPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeAdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSession(
        string $role,
        string $token
    ): Account {
        $account = Account::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'profile_name' => 'Test Admin',
            'email' => strtolower($role).'@example.com',
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        Session::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $account;
    }

    public function test_root_super_admin_receives_full_permission_catalog(): void
    {
        $account = $this->createAccountWithSession(
            'ROOT_SUPER_ADMIN',
            'root-permission-token'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer root-permission-token'
        )->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.account.permissions',
                app(AdminPermissionService::class)->permissionKeys()
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
