<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogCreationTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Shared helpers: AuditLogTest
    |--------------------------------------------------------------------------
    */

    private function grantPermissions(Account $account, array $permissions): void
    {
        foreach ($permissions as $permission) {
            AccountPermission::create([
                'account_id' => $account->id,
                'permission_key' => $permission,
            ]);
        }
    }

    private function createSessionForAccount(
        Account $account,
        string $token,
        string $sessionId
    ): void {
        Session::create([
            'id' => $sessionId,
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function createAccount(
        string $id,
        string $name,
        string $phone,
        string $email,
        string $role,
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function createRootSuperAdmin(): array
    {
        $root = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $token = 'audit-root-super-admin-token';

        $this->createSessionForAccount(
            $root,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        return [$root, $token];
    }

    private function createSuperAdmin(): array
    {
        $admin = $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Super Admin',
            '60222222222',
            'superadmin@example.com',
            'SUPER_ADMIN'
        );

        $this->grantPermissions($admin, [
            'accounts.suspend',
            'accounts.reactivate',
            'accounts.unsuspend',
        ]);

        $token = 'audit-super-admin-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        return [$admin, $token];
    }

    private function createUser(): Account
    {
        return $this->createAccount(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'Test User',
            '60333333333',
            'user@example.com',
            'USER'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AuditLogTest: Admin action audit creation
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_action_creates_audit_log_with_reason(): void
    {
        [$admin, $token] = $this->createSuperAdmin();
        $user = $this->createUser();

        $reason = 'Violation of usage policy.';

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/suspend', [
            'reason' => $reason,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => $reason,
        ]);
    }

    public function test_root_super_admin_action_creates_audit_log_with_reason(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $admin = $this->createAccount(
            'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'Managed Super Admin',
            '60444444444',
            'managed-superadmin@example.com',
            'SUPER_ADMIN'
        );

        $reason = 'Administrative review required.';

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$admin->id.'/suspend', [
            'reason' => $reason,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $admin->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => $reason,
        ]);
    }

    public function test_failed_admin_action_does_not_create_audit_log(): void
    {
        [$admin, $token] = $this->createSuperAdmin();

        $target = $this->createAccount(
            '11111111-1111-4111-8111-111111111111',
            'Target Super Admin',
            '60555555555',
            'target-superadmin@example.com',
            'SUPER_ADMIN'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$target->id.'/suspend', [
            'reason' => 'Unauthorized suspension attempt.',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
        ]);
    }

    public function test_missing_reason_does_not_create_audit_log(): void
    {
        [$admin, $token] = $this->createSuperAdmin();
        $user = $this->createUser();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/suspend');

        $response->assertStatus(422);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_SUSPENDED',
        ]);
    }

    public function test_blank_reason_does_not_create_audit_log(): void
    {
        [$admin, $token] = $this->createSuperAdmin();
        $user = $this->createUser();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/suspend', [
            'reason' => '   ',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_SUSPENDED',
        ]);
    }

    public function test_audit_log_records_created_at(): void
    {
        [$admin, $token] = $this->createSuperAdmin();
        $user = $this->createUser();

        $reason = 'Account requires administrative suspension.';

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/suspend', [
            'reason' => $reason,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => $reason,
        ]);

        $this->assertNotNull(
            \DB::table('audit_logs')
                ->where('actor_account_id', $admin->id)
                ->where('target_account_id', $user->id)
                ->where('action', 'ACCOUNT_SUSPENDED')
                ->value('created_at')
        );
    }

    public function test_reactivation_creates_audit_log_with_reason(): void
    {
        [$admin, $token] = $this->createSuperAdmin();

        $user = $this->createAccount(
            '22222222-2222-4222-8222-222222222222',
            'Deactivated User',
            '60666666666',
            'deactivated-user@example.com',
            'USER',
            'DEACTIVATED'
        );

        $reason = 'User reactivation approved.';

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/reactivate', [
            'reason' => $reason,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_REACTIVATED',
            'reason' => $reason,
        ]);
    }

    public function test_unsuspension_creates_audit_log_with_reason(): void
    {
        [$admin, $token] = $this->createSuperAdmin();

        $user = $this->createAccount(
            '33333333-3333-4333-8333-333333333333',
            'Suspended User',
            '60777777777',
            'suspended-user@example.com',
            'USER',
            'SUSPENDED'
        );

        $reason = 'Suspension issue resolved.';

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/unsuspend', [
            'reason' => $reason,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_UNSUSPENDED',
            'reason' => $reason,
        ]);
    }

    public function test_reactivation_requires_reason(): void
    {
        [$admin, $token] = $this->createSuperAdmin();

        $user = $this->createAccount(
            '44444444-4444-4444-8444-444444444444',
            'Deactivated User Without Reason',
            '60888888888',
            'deactivated-no-reason@example.com',
            'USER',
            'DEACTIVATED'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/reactivate');

        $response->assertStatus(422);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_REACTIVATED',
        ]);
    }

    public function test_unsuspension_requires_reason(): void
    {
        [$admin, $token] = $this->createSuperAdmin();

        $user = $this->createAccount(
            '55555555-5555-4555-8555-555555555555',
            'Suspended User Without Reason',
            '60999999999',
            'suspended-no-reason@example.com',
            'USER',
            'SUSPENDED'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/unsuspend');

        $response->assertStatus(422);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_UNSUSPENDED',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | AccountLifecycleAuditTest: Self-service lifecycle audit
    |--------------------------------------------------------------------------
    */

    private function createAccountWithSession(
        string $accountId,
        string $name,
        string $phone,
        string $email,
        string $token,
        string $sessionId
    ): Account {
        $account = Account::create([
            'id' => $accountId,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        Session::create([
            'id' => $sessionId,
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $account;
    }

    public function test_deactivation_records_audit_event(): void
    {
        $token = 'account-lifecycle-deactivation-token';

        $account = $this->createAccountWithSession(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Deactivation Audit User',
            '60111111111',
            'deactivation-audit@example.com',
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/deactivate');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deactivated successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DEACTIVATED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DEACTIVATED',
            'reason' => 'Account deactivated by account owner.',
        ]);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
        ]);

        $session = Session::where('account_id', $account->id)
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        $this->assertNotNull($session->ended_at);
    }

    public function test_deletion_records_audit_event(): void
    {
        $token = 'account-lifecycle-deletion-token';

        $account = $this->createAccountWithSession(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Deletion Audit User',
            '60222222222',
            'deletion-audit@example.com',
            $token,
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/delete');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DELETED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DELETED',
            'reason' => 'Account marked as deleted by account owner.',
        ]);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
        ]);

        $session = Session::where('account_id', $account->id)
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        $this->assertNotNull($session->ended_at);
    }

    /*
    |--------------------------------------------------------------------------
    | SuperAdminPermissionAuditTest: Permission audit
    |--------------------------------------------------------------------------
    */

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

    private function createPermissionAuditSuperAdmin(): Account
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
        $admin = $this->createPermissionAuditSuperAdmin();

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
        $admin = $this->createPermissionAuditSuperAdmin();

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
        $admin = $this->createPermissionAuditSuperAdmin();

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
