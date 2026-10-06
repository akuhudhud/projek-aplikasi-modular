<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use RefreshDatabase;

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
}
