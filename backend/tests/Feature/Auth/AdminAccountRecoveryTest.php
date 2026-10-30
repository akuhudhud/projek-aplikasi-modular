<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function grantPermission(Account $account, string $permission): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => $permission,
        ]);
    }

    private function createSessionForAccount(Account $account, string $token, string $sessionId): void
    {
        Session::create([
            'id' => $sessionId,
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function createSuperAdminWithSession(): array
    {
        $admin = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Super Admin',
            'phone' => '60111111111',
            'email' => 'admin@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->grantPermission($admin, 'accounts.reactivate');

        $token = 'super-admin-session-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        return [$admin, $token];
    }

    private function createDeactivatedUser(): Account
    {
        return Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'name' => 'Deactivated User',
            'phone' => '60222222222',
            'email' => 'deactivated@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'DEACTIVATED',
        ]);
    }

    public function test_super_admin_can_reactivate_deactivated_account(): void
    {
        [$admin, $token] = $this->createSuperAdminWithSession();
        $account = $this->createDeactivatedUser();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$account->id.'/reactivate', [
            'reason' => 'Reactivation approved by administrator.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account reactivated successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_reactivated_account_can_login_again(): void
    {
        [$admin, $token] = $this->createSuperAdminWithSession();
        $account = $this->createDeactivatedUser();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$account->id.'/reactivate', [
            'reason' => 'Reactivation approved by administrator.',
        ])->assertStatus(200);

        $response = $this->postJson('/api/login', [
            'phone' => '60222222222',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
            ]);
    }

    public function test_regular_user_cannot_reactivate_account(): void
    {
        $adminAccount = $this->createDeactivatedUser();

        $user = Account::create([
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'name' => 'Regular User',
            'phone' => '60333333333',
            'email' => 'regular@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'regular-user-session-token';

        $this->createSessionForAccount(
            $user,
            $token,
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$adminAccount->id.'/reactivate');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'required_permission' => 'accounts.reactivate',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $adminAccount->id,
            'status' => 'DEACTIVATED',
        ]);
    }

    public function test_reactivation_requires_session_token(): void
    {
        $account = $this->createDeactivatedUser();

        $response = $this->postJson(
            '/api/admin/accounts/'.$account->id.'/reactivate'
        );

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_active_account_cannot_be_reactivated(): void
    {
        [$admin, $token] = $this->createSuperAdminWithSession();

        $account = Account::create([
            'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'name' => 'Active User',
            'phone' => '60444444444',
            'email' => 'active@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$account->id.'/reactivate');

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Account is already active.',
            ]);
    }

    public function test_suspended_account_cannot_be_reactivated(): void
    {
        [$admin, $token] = $this->createSuperAdminWithSession();

        $account = Account::create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Suspended User',
            'phone' => '60555555555',
            'email' => 'suspended@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$account->id.'/reactivate', [
            'reason' => 'Attempted invalid lifecycle transition.',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Only deactivated accounts can be reactivated.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'SUSPENDED',
        ]);
    }

    public function test_deleted_account_cannot_be_reactivated(): void
    {
        [$admin, $token] = $this->createSuperAdminWithSession();

        $account = Account::create([
            'id' => '22222222-2222-4222-8222-222222222222',
            'name' => 'Deleted User',
            'phone' => '60666666666',
            'email' => 'deleted@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'DELETED',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$account->id.'/reactivate', [
            'reason' => 'Attempted invalid lifecycle transition.',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Only deactivated accounts can be reactivated.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DELETED',
        ]);
    }

    public function test_super_admin_can_unsuspend_suspended_user(): void
    {
        $admin = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'phone' => '0190000001',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->grantPermission($admin, 'accounts.unsuspend');

        $user = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Suspended User',
            'phone' => '0190000002',
            'email' => 'user@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);

        $adminToken = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $adminToken),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withToken($adminToken)
            ->postJson("/api/admin/accounts/{$user->id}/unsuspend", [
                'reason' => 'Administrative review completed.',
            ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account unsuspended successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_regular_user_cannot_unsuspend_account(): void
    {
        $user = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Regular User',
            'phone' => '0190000011',
            'email' => 'regular@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $target = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Suspended User',
            'phone' => '0190000012',
            'email' => 'suspended@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);

        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withToken($token)
            ->postJson("/api/admin/accounts/{$target->id}/unsuspend");

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'required_permission' => 'accounts.unsuspend',
            ]);
    }

    public function test_unsuspend_requires_session_token(): void
    {
        $target = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Suspended User',
            'phone' => '0190000021',
            'email' => 'suspended2@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);

        $response = $this->postJson(
            "/api/admin/accounts/{$target->id}/unsuspend"
        );

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_active_account_cannot_be_unsuspended(): void
    {
        $admin = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'phone' => '0190000031',
            'email' => 'admin2@example.com',
            'password' => 'password123',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $target = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Active User',
            'phone' => '0190000032',
            'email' => 'active@example.com',
            'password' => 'password123',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $this->grantPermission($admin, 'accounts.unsuspend');

        $adminToken = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $adminToken),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withToken($adminToken)
            ->postJson("/api/admin/accounts/{$target->id}/unsuspend");

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Only suspended accounts can be unsuspended.',
            ]);
    }

    public function test_unsuspend_nonexistent_account_returns_not_found(): void
    {
        $admin = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'phone' => '0190000041',
            'email' => 'admin3@example.com',
            'password' => 'password123',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->grantPermission($admin, 'accounts.unsuspend');

        $adminToken = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $adminToken),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withToken($adminToken)
            ->postJson(
                '/api/admin/accounts/non-existent-account/unsuspend'
            );

        $response
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Account not found.',
            ]);
    }
}
