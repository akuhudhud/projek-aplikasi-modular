<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
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
}
