<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use RefreshDatabase;

    private function grantPermission(
        Account $account,
        string $permission
    ): void {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => $permission,
        ]);
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

        $token = 'audit-access-root-token';

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

        $this->grantPermission($admin, 'audit_logs.view');

        $token = 'audit-access-super-admin-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        return [$admin, $token];
    }

    private function createUser(): array
    {
        $user = $this->createAccount(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'Test User',
            '60333333333',
            'user@example.com',
            'USER'
        );

        $token = 'audit-access-user-token';

        $this->createSessionForAccount(
            $user,
            $token,
            'ffffffff-ffff-4fff-8fff-ffffffffffff'
        );

        return [$user, $token];
    }

    private function seedAuditLogs(
        Account $root,
        Account $admin,
        Account $user
    ): void {
        AuditLog::create([
            'actor_account_id' => $root->id,
            'target_account_id' => $admin->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Administrative review required.',
        ]);

        AuditLog::create([
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_REACTIVATED',
            'reason' => 'User reactivation approved.',
        ]);

        AuditLog::create([
            'actor_account_id' => $admin->id,
            'target_account_id' => $user->id,
            'action' => 'ACCOUNT_UNSUSPENDED',
            'reason' => 'Suspension issue resolved.',
        ]);
    }

    public function test_root_super_admin_can_read_audit_logs(): void
    {
        [$root, $rootToken] = $this->createRootSuperAdmin();
        [$admin] = $this->createSuperAdmin();
        [$user] = $this->createUser();

        $this->seedAuditLogs($root, $admin, $user);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$rootToken
        )->getJson('/api/admin/audit-logs');

        $response->assertStatus(200);

        $response->assertJson([
            'success' => true,
        ]);

        $response->assertJsonCount(
            3,
            'data.audit_logs'
        );
    }

    public function test_super_admin_can_read_audit_logs(): void
    {
        [$root] = $this->createRootSuperAdmin();
        [$admin, $adminToken] = $this->createSuperAdmin();
        [$user] = $this->createUser();

        $this->seedAuditLogs($root, $admin, $user);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$adminToken
        )->getJson('/api/admin/audit-logs');

        $response->assertStatus(200);

        $response->assertJson([
            'success' => true,
        ]);

        $response->assertJsonCount(
            3,
            'data.audit_logs'
        );
    }

    public function test_regular_user_cannot_read_audit_logs(): void
    {
        [$root] = $this->createRootSuperAdmin();
        [$admin] = $this->createSuperAdmin();
        [$user, $userToken] = $this->createUser();

        $this->seedAuditLogs($root, $admin, $user);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$userToken
        )->getJson('/api/admin/audit-logs');

        $response->assertStatus(403);
    }

    public function test_audit_log_access_requires_session_token(): void
    {
        $response = $this->getJson('/api/admin/audit-logs');

        $response->assertStatus(401);
    }

    public function test_audit_log_response_contains_expected_fields(): void
    {
        [$root, $rootToken] = $this->createRootSuperAdmin();
        [$admin] = $this->createSuperAdmin();
        [$user] = $this->createUser();

        $this->seedAuditLogs($root, $admin, $user);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$rootToken
        )->getJson('/api/admin/audit-logs');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'success',
            'data' => [
                'audit_logs' => [
                    '*' => [
                        'id',
                        'actor_account_id',
                        'target_account_id',
                        'action',
                        'reason',
                        'created_at',
                    ],
                ],
            ],
        ]);

        $response->assertJsonFragment([
            'reason' => 'Administrative review required.',
        ]);
    }

    public function test_admin_can_read_audit_log_after_actor_account_is_physically_deleted(): void
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'audit-actor-admin@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $actor = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Deleted Audit Actor',
            '60222222222',
            'deleted-audit-actor@example.com',
            'USER'
        );

        $target = $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Remaining Audit Target',
            '60333333333',
            'remaining-audit-target@example.com',
            'USER'
        );

        $token = 'audit-actor-deletion-access-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        $auditLog = AuditLog::create([
            'actor_account_id' => $actor->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_UPDATED',
            'reason' => 'Account update recorded before actor deletion.',
        ]);

        $auditLogId = $auditLog->id;
        $actorId = $actor->id;
        $targetId = $target->id;

        $actor->delete();

        $this->assertDatabaseMissing('accounts', [
            'id' => $actorId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLogId,
            'actor_account_id' => $actorId,
            'target_account_id' => $targetId,
            'action' => 'ACCOUNT_UPDATED',
            'reason' => 'Account update recorded before actor deletion.',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/audit-logs');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'id' => $auditLogId,
                'actor_account_id' => $actorId,
                'target_account_id' => $targetId,
                'action' => 'ACCOUNT_UPDATED',
                'reason' => 'Account update recorded before actor deletion.',
            ]);
    }

    public function test_admin_can_read_audit_log_after_target_account_is_physically_deleted(): void
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'audit-retention-admin@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $target = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Deleted Target',
            '60222222222',
            'audit-retention-target@example.com',
            'USER'
        );

        $token = 'audit-retention-access-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc'
        );

        $auditLog = AuditLog::create([
            'actor_account_id' => $admin->id,
            'target_account_id' => $target->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Account suspended following an administrative review.',
        ]);

        $auditLogId = $auditLog->id;
        $adminId = $admin->id;
        $targetId = $target->id;

        $target->delete();

        $this->assertDatabaseMissing('accounts', [
            'id' => $targetId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLogId,
            'actor_account_id' => $adminId,
            'target_account_id' => $targetId,
            'action' => 'ACCOUNT_SUSPENDED',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/audit-logs');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'id' => $auditLogId,
                'actor_account_id' => $adminId,
                'target_account_id' => $targetId,
                'action' => 'ACCOUNT_SUSPENDED',
                'reason' => 'Account suspended following an administrative review.',
            ]);
    }
}
