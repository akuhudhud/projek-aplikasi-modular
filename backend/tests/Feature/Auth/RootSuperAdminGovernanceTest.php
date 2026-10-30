<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
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
            'name' => 'Test '.$role,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        $token = 'protection-token-'.$accountId;

        Session::create([
            'id' => $sessionId,
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$account, $token];
    }

    public function test_root_super_admin_can_create_super_admin(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Super Admin created successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'SUPER_ADMIN_CREATED',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);
    }

    public function test_super_admin_cannot_create_super_admin(): void
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Super Admin',
            '60111111111',
            'admin@example.com',
            'SUPER_ADMIN'
        );

        $token = 'super-admin-creation-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'Another Super Admin',
            'phone' => '60222222222',
            'email' => 'anotheradmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Attempted unauthorized Super Admin creation.',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'anotheradmin@example.com',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }

    public function test_regular_user_cannot_create_super_admin(): void
    {
        $user = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Regular User',
            '60111111111',
            'user@example.com',
            'USER'
        );

        $token = 'regular-user-creation-token';

        $this->createSessionForAccount(
            $user,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'Another Super Admin',
            'phone' => '60222222222',
            'email' => 'anotheradmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Attempted unauthorized Super Admin creation.',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'anotheradmin@example.com',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }

    public function test_super_admin_creation_requires_session_token(): void
    {
        $response = $this->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_super_admin_creation_requires_reason(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseMissing('accounts', [
            'email' => 'newadmin@example.com',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }

    public function test_super_admin_creation_rejects_blank_reason(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => '   ',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseMissing('accounts', [
            'email' => 'newadmin@example.com',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $root->id,
            'action' => 'SUPER_ADMIN_CREATED',
        ]);
    }

    public function test_created_super_admin_response_contains_safe_fields_only(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'account' => [
                        'id',
                        'name',
                        'job_title',
                        'phone',
                        'email',
                        'profile_picture',
                        'role',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $response
            ->assertJsonMissingPath('data.account.password')
            ->assertJsonMissingPath('data.account.reason')
            ->assertJsonMissingPath('data.account.token_hash')
            ->assertJsonMissingPath('data.account.failed_login_attempts')
            ->assertJsonMissingPath('data.account.locked_until');

        $response->assertJsonMissing([
            'password' => 'Password1',
        ]);

        $response->assertJsonMissing([
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response->assertJsonMissing([
            'token_hash' => hash('sha256', 'root-super-admin-governance-token'),
        ]);
    }

    public function test_created_super_admin_has_no_session_created_automatically(): void
    {
        [$root, $token] = $this->createRootSuperAdmin();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'New Super Admin',
            'phone' => '60222222222',
            'email' => 'newadmin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Super Admin appointed for platform administration.',
        ]);

        $response->assertStatus(201);

        $newAdmin = Account::query()
            ->where('email', 'newadmin@example.com')
            ->firstOrFail();

        $this->assertDatabaseCount('sessions', 1);

        $this->assertDatabaseHas('accounts', [
            'id' => $newAdmin->id,
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $newAdmin->id,
            'action' => 'SUPER_ADMIN_CREATED',
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
        [$root, $token] = $this->createRootSuperAdmin();
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
        [$root, $token] = $this->createRootSuperAdmin();

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
        [$root, $token] = $this->createRootSuperAdmin();

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
