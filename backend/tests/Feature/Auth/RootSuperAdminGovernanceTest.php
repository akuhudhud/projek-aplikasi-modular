<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RootSuperAdminGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function grantPermission(Account $account, string $permission): void
    {
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
        string $profileName,
        string $phone,
        string $email,
        string $role,
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => $id,
            'profile_name' => $profileName,
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

        $token = 'root-super-admin-governance-token';

        $this->createSessionForAccount(
            $root,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        return [$root, $token];
    }

    private function createSuperAdmin(): Account
    {
        return $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Super Admin',
            '60222222222',
            'superadmin@example.com',
            'SUPER_ADMIN'
        );
    }

    private function createSessionForProtection(
        string $accountId,
        string $phone,
        string $email,
        string $sessionId,
        string $role
    ): array {
        $account = Account::create([
            'id' => $accountId,
            'profile_name' => 'Test '.$role,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        $token = 'protection-token-'.$accountId;

        $this->createSessionForAccount(
            $account,
            $token,
            $sessionId
        );

        return [$account, $token];
    }

    public function test_root_super_admin_can_appoint_existing_user_as_super_admin(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $user = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Existing User',
            '60333333333',
            'existing-user@example.com',
            'USER'
        );

        $originalId = $user->id;
        $originalPassword = $user->password;

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Operations Manager',
                'reason' => 'Appointment approved by Root Super Admin.',
            ]
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas('accounts', [
            'id' => $originalId,
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
            'profile_name' => 'Existing User',
            'phone' => '60333333333',
            'email' => 'existing-user@example.com',
            'job_title' => 'Operations Manager',
        ]);

        $user->refresh();

        $this->assertSame($originalId, $user->id);
        $this->assertSame($originalPassword, $user->password);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $user->id,
            'action' => 'SUPER_ADMIN_APPOINTED',
            'reason' => 'Appointment approved by Root Super Admin.',
        ]);
    }

    public function test_super_admin_cannot_appoint_super_admin(): void
    {
        $admin = $this->createSuperAdmin();

        $token = 'super-admin-appointment-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee'
        );

        $user = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Existing User',
            '60333333333',
            'existing-user@example.com',
            'USER'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Manager',
                'reason' => 'Unauthorized appointment attempt.',
            ]
        );

        $response->assertStatus(403);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_regular_user_cannot_appoint_super_admin(): void
    {
        $user = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Regular User',
            '60111111111',
            'user@example.com',
            'USER'
        );

        $token = 'regular-user-appointment-token';

        $this->createSessionForAccount(
            $user,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $target = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Target User',
            '60333333333',
            'target-user@example.com',
            'USER'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$target->id.'/appoint-super-admin',
            [
                'job_title' => 'Manager',
                'reason' => 'Unauthorized appointment attempt.',
            ]
        );

        $response->assertStatus(403);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'role' => 'USER',
        ]);
    }

    public function test_super_admin_appointment_requires_session_token(): void
    {
        $user = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Existing User',
            '60333333333',
            'existing-user@example.com',
            'USER'
        );

        $response = $this->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Manager',
                'reason' => 'Approved appointment.',
            ]
        );

        $response->assertStatus(401);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
        ]);
    }

    public function test_super_admin_appointment_requires_reason(): void
    {
        [, $token] = $this->createRootSuperAdmin();

        $user = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Existing User',
            '60333333333',
            'existing-user@example.com',
            'USER'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Manager',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
        ]);
    }

    public function test_super_admin_appointment_rejects_blank_reason(): void
    {
        [, $token] = $this->createRootSuperAdmin();

        $user = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Existing User',
            '60333333333',
            'existing-user@example.com',
            'USER'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Manager',
                'reason' => '   ',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
        ]);
    }

    public function test_super_admin_appointment_response_contains_safe_fields_only(): void
    {
        [, $token] = $this->createRootSuperAdmin();

        $user = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Existing User',
            '60333333333',
            'existing-user@example.com',
            'USER'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Operations Manager',
                'reason' => 'Approved appointment.',
            ]
        );

        $response->assertSuccessful();

        $response->assertJsonMissingPath('data.password');
        $response->assertJsonMissingPath('data.password_hash');
        $response->assertJsonMissingPath('data.token_hash');
        $response->assertJsonMissingPath('data.remember_token');
    }

    public function test_super_admin_appointment_preserves_existing_sessions(): void
    {
        [, $token] = $this->createRootSuperAdmin();

        $user = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Existing User',
            '60333333333',
            'existing-user@example.com',
            'USER'
        );

        $existingSessionToken = 'existing-user-session-token';
        $existingSessionId = 'ffffffff-ffff-4fff-8fff-ffffffffffff';

        $this->createSessionForAccount(
            $user,
            $existingSessionToken,
            $existingSessionId
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Operations Manager',
                'reason' => 'Approved appointment.',
            ]
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas('sessions', [
            'id' => $existingSessionId,
            'account_id' => $user->id,
            'token_hash' => hash('sha256', $existingSessionToken),
            'ended_at' => null,
        ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'SUPER_ADMIN',
        ]);
    }

    public function test_root_super_admin_can_suspend_own_account(): void
    {
        [$root, $token] = $this->createSessionForProtection(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            '60111111111',
            'root-one@example.com',
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'ROOT_SUPER_ADMIN'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$root->id.'/suspend',
            [
                'reason' => 'Root initiated account suspension.',
            ]
        );

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Account suspended successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $root->id,
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'SUSPENDED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $root->id,
            'action' => 'ACCOUNT_SUSPENDED',
            'reason' => 'Root initiated account suspension.',
        ]);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $root->id,
            'ended_at' => now(),
        ]);
    }

    public function test_root_super_admin_can_suspend_super_admin(): void
    {
        [, $token] = $this->createRootSuperAdmin();
        $superAdmin = $this->createSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$superAdmin->id.'/suspend', [
            'reason' => 'Administrative review required.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account suspended successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $superAdmin->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'SUSPENDED',
        ]);
    }

    public function test_super_admin_cannot_suspend_another_super_admin(): void
    {
        $admin = $this->createSuperAdmin();
        $this->grantPermission($admin, 'accounts.suspend');

        $target = $this->createAccount(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'Target Super Admin',
            '60333333333',
            'target-superadmin@example.com',
            'SUPER_ADMIN'
        );

        $token = 'regular-super-admin-control-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$target->id.'/suspend', [
            'reason' => 'Attempted unauthorized suspension.',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_super_admin_cannot_suspend_root_super_admin(): void
    {
        $admin = $this->createSuperAdmin();
        $this->grantPermission($admin, 'accounts.suspend');

        $root = $this->createAccount(
            'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'Root Super Admin',
            '60444444444',
            'root-target@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $token = 'super-admin-root-control-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            '11111111-1111-4111-8111-111111111111'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$root->id.'/suspend', [
            'reason' => 'Attempted unauthorized suspension.',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $root->id,
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_root_super_admin_can_unsuspend_super_admin(): void
    {
        [, $token] = $this->createRootSuperAdmin();

        $superAdmin = $this->createAccount(
            '22222222-2222-4222-8222-222222222222',
            'Suspended Super Admin',
            '60555555555',
            'suspended-superadmin@example.com',
            'SUPER_ADMIN',
            'SUSPENDED'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$superAdmin->id.'/unsuspend', [
            'reason' => 'Administrative review completed.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account unsuspended successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $superAdmin->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_super_admin_cannot_unsuspend_another_super_admin(): void
    {
        $admin = $this->createSuperAdmin();
        $this->grantPermission($admin, 'accounts.unsuspend');

        $target = $this->createAccount(
            '33333333-3333-4333-8333-333333333333',
            'Suspended Target Super Admin',
            '60666666666',
            'suspended-target@example.com',
            'SUPER_ADMIN',
            'SUSPENDED'
        );

        $token = 'regular-super-admin-unsuspend-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            '44444444-4444-4444-8444-444444444444'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$target->id.'/unsuspend', [
            'reason' => 'Attempted unauthorized unsuspension.',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'SUSPENDED',
        ]);
    }

    public function test_root_super_admin_can_reactivate_super_admin(): void
    {
        [, $token] = $this->createRootSuperAdmin();

        $superAdmin = $this->createAccount(
            '55555555-5555-4555-8555-555555555555',
            'Deactivated Super Admin',
            '60777777777',
            'deactivated-superadmin@example.com',
            'SUPER_ADMIN',
            'DEACTIVATED'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$superAdmin->id.'/reactivate', [
            'reason' => 'Reactivation approved by root administrator.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account reactivated successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $superAdmin->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_super_admin_cannot_reactivate_another_super_admin(): void
    {
        $admin = $this->createSuperAdmin();
        $this->grantPermission($admin, 'accounts.reactivate');

        $target = $this->createAccount(
            '66666666-6666-4666-8666-666666666666',
            'Deactivated Target Super Admin',
            '60888888888',
            'deactivated-target@example.com',
            'SUPER_ADMIN',
            'DEACTIVATED'
        );

        $token = 'regular-super-admin-reactivate-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            '77777777-7777-4777-8777-777777777777'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$target->id.'/reactivate', [
            'reason' => 'Attempted unauthorized reactivation.',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'DEACTIVATED',
        ]);
    }
}
