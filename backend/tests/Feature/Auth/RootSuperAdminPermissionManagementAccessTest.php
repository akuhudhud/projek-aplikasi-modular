<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RootSuperAdminPermissionManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(string $role, string $email): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Permission Test '.$role,
            'phone' => '60'.random_int(100000000, 999999999),
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(Account $account): string
    {
        $token = 'permission-management-'.Str::random(32);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $token;
    }

    private function createTargetSuperAdmin(): Account
    {
        return $this->createAccount(
            'SUPER_ADMIN',
            'permission-target@example.com'
        );
    }

    public function test_root_can_view_super_admin_permission_catalog_and_assignments(): void
    {
        $root = $this->createAccount(
            'ROOT_SUPER_ADMIN',
            'permission-root@example.com'
        );

        $target = $this->createTargetSuperAdmin();

        AccountPermission::create([
            'account_id' => $target->id,
            'permission_key' => 'accounts.view',
        ]);

        $token = $this->authenticate($root);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson(
            '/api/admin/accounts/'.$target->id.'/permissions'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.id', $target->id)
            ->assertJsonFragment([
                'key' => 'accounts.view',
                'label' => 'Melihat direktori akaun',
            ])
            ->assertJsonPath(
                'data.permissions',
                ['accounts.view']
            );
    }

    public function test_appointed_super_admin_cannot_view_permission_management(): void
    {
        $actor = $this->createAccount(
            'SUPER_ADMIN',
            'permission-appointed@example.com'
        );

        $target = $this->createTargetSuperAdmin();

        $token = $this->authenticate($actor);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson(
            '/api/admin/accounts/'.$target->id.'/permissions'
        )
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_regular_user_cannot_view_permission_management(): void
    {
        $user = $this->createAccount(
            'USER',
            'permission-user@example.com'
        );

        $target = $this->createTargetSuperAdmin();

        $token = $this->authenticate($user);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson(
            '/api/admin/accounts/'.$target->id.'/permissions'
        )
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_appointed_super_admin_cannot_change_another_super_admin_permissions(): void
    {
        $actor = $this->createAccount(
            'SUPER_ADMIN',
            'permission-change-actor@example.com'
        );

        $target = $this->createTargetSuperAdmin();

        AccountPermission::create([
            'account_id' => $target->id,
            'permission_key' => 'accounts.view',
        ]);

        $token = $this->authenticate($actor);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->putJson(
            '/api/admin/accounts/'.$target->id.'/permissions',
            [
                'permissions' => ['audit_logs.view'],
                'reason' => 'Percubaan perubahan permission.',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized.');

        $this->assertDatabaseHas('account_permissions', [
            'account_id' => $target->id,
            'permission_key' => 'accounts.view',
        ]);

        $this->assertDatabaseMissing('account_permissions', [
            'account_id' => $target->id,
            'permission_key' => 'audit_logs.view',
        ]);

        $this->assertSame(
            0,
            AuditLog::query()
                ->where('actor_account_id', $actor->id)
                ->where('target_account_id', $target->id)
                ->where('action', 'ADMIN_PERMISSIONS_UPDATED')
                ->count()
        );
    }

    public function test_regular_user_cannot_change_super_admin_permissions(): void
    {
        $user = $this->createAccount(
            'USER',
            'permission-change-user@example.com'
        );

        $target = $this->createTargetSuperAdmin();

        $token = $this->authenticate($user);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->putJson(
            '/api/admin/accounts/'.$target->id.'/permissions',
            [
                'permissions' => ['accounts.view'],
                'reason' => 'Percubaan perubahan permission.',
            ]
        )
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized.');

        $this->assertDatabaseMissing('account_permissions', [
            'account_id' => $target->id,
            'permission_key' => 'accounts.view',
        ]);
    }

    public function test_permission_management_requires_authentication(): void
    {
        $target = $this->createTargetSuperAdmin();

        $this->getJson(
            '/api/admin/accounts/'.$target->id.'/permissions'
        )->assertUnauthorized();

        $this->putJson(
            '/api/admin/accounts/'.$target->id.'/permissions',
            [
                'permissions' => ['accounts.view'],
                'reason' => 'Ujian tanpa sesi.',
            ]
        )->assertUnauthorized();
    }
}
