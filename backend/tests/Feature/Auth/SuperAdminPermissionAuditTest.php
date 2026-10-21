<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminPermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    private function createRootWithSession(): array
    {
        $root = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Root Owner',
            'phone' => '60111111111',
            'email' => 'root@example.com',
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $token = 'root-permission-audit-test-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $root->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$root, $token];
    }

    private function createSuperAdmin(): Account
    {
        return Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'name' => 'Appointed Super Admin',
            'phone' => '60222222222',
            'email' => 'admin@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_multiple_permission_changes_create_exactly_one_audit_record(): void
    {
        [, $token] = $this->createRootWithSession();
        $admin = $this->createSuperAdmin();

        AccountPermission::create([
            'account_id' => $admin->id,
            'permission_key' => 'accounts.view',
        ]);

        AccountPermission::create([
            'account_id' => $admin->id,
            'permission_key' => 'accounts.suspend',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->putJson(
            '/api/admin/accounts/'.$admin->id.'/permissions',
            [
                'permissions' => [
                    'accounts.view',
                    'accounts.unsuspend',
                    'audit_logs.view',
                ],
                'reason' => 'Pelarasan akses pentadbiran.',
            ]
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.changes.granted',
                ['accounts.unsuspend', 'audit_logs.view']
            )
            ->assertJsonPath(
                'data.changes.revoked',
                ['accounts.suspend']
            );

        $this->assertDatabaseHas('account_permissions', [
            'account_id' => $admin->id,
            'permission_key' => 'accounts.view',
        ]);

        $this->assertDatabaseHas('account_permissions', [
            'account_id' => $admin->id,
            'permission_key' => 'accounts.unsuspend',
        ]);

        $this->assertDatabaseHas('account_permissions', [
            'account_id' => $admin->id,
            'permission_key' => 'audit_logs.view',
        ]);

        $this->assertDatabaseMissing('account_permissions', [
            'account_id' => $admin->id,
            'permission_key' => 'accounts.suspend',
        ]);

        $this->assertSame(
            1,
            AuditLog::query()
                ->where('actor_account_id', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')
                ->where('target_account_id', $admin->id)
                ->count()
        );

        $audit = AuditLog::query()
            ->where('actor_account_id', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')
            ->where('target_account_id', $admin->id)
            ->firstOrFail();

        $this->assertSame(
            'ADMIN_PERMISSIONS_UPDATED',
            $audit->action
        );

        $this->assertStringContainsString(
            'Pelarasan akses pentadbiran.',
            $audit->reason
        );

        $this->assertStringContainsString(
            'Granted: accounts.unsuspend, audit_logs.view',
            $audit->reason
        );

        $this->assertStringContainsString(
            'Revoked: accounts.suspend',
            $audit->reason
        );
    }

    public function test_invalid_permission_creates_no_permission_change_or_audit_record(): void
    {
        [, $token] = $this->createRootWithSession();
        $admin = $this->createSuperAdmin();

        AccountPermission::create([
            'account_id' => $admin->id,
            'permission_key' => 'accounts.view',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->putJson(
            '/api/admin/accounts/'.$admin->id.'/permissions',
            [
                'permissions' => [
                    'accounts.suspend',
                    'permission.not.real',
                ],
                'reason' => 'Ujian input tidak sah.',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('account_permissions', [
            'account_id' => $admin->id,
            'permission_key' => 'accounts.view',
        ]);

        $this->assertDatabaseMissing('account_permissions', [
            'account_id' => $admin->id,
            'permission_key' => 'accounts.suspend',
        ]);

        $this->assertSame(
            0,
            AuditLog::query()
                ->where('actor_account_id', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')
                ->where('target_account_id', $admin->id)
                ->count()
        );
    }

    public function test_no_op_permission_update_creates_no_audit_record(): void
    {
        [, $token] = $this->createRootWithSession();
        $admin = $this->createSuperAdmin();

        AccountPermission::create([
            'account_id' => $admin->id,
            'permission_key' => 'accounts.view',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->putJson(
            '/api/admin/accounts/'.$admin->id.'/permissions',
            [
                'permissions' => ['accounts.view'],
                'reason' => 'Tiada perubahan akses.',
            ]
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.changes.granted', [])
            ->assertJsonPath('data.changes.revoked', []);

        $this->assertSame(
            0,
            AuditLog::query()
                ->where('actor_account_id', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')
                ->where('target_account_id', $admin->id)
                ->count()
        );

        $this->assertDatabaseHas('account_permissions', [
            'account_id' => $admin->id,
            'permission_key' => 'accounts.view',
        ]);
    }
}
