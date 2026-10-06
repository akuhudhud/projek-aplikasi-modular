<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAccountManagementTest extends TestCase
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

        $token = 'super-admin-management-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        return [$admin, $token];
    }

    private function createUser(): Account
    {
        return Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'name' => 'Test User',
            'phone' => '60222222222',
            'email' => 'user@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_super_admin_can_suspend_active_user(): void
    {
        [$admin, $token] = $this->createSuperAdminWithSession();
        $user = $this->createUser();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/suspend', [
            'reason' => 'Administrative review required.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account suspended successfully.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'status' => 'SUSPENDED',
        ]);
    }

    public function test_suspension_ends_target_active_session(): void
    {
        [$admin, $token] = $this->createSuperAdminWithSession();
        $user = $this->createUser();

        $userToken = 'user-active-session-token';

        $this->createSessionForAccount(
            $user,
            $userToken,
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/suspend', [
            'reason' => 'Administrative review required.',
        ])->assertStatus(200);

        $session = Session::where('account_id', $user->id)
            ->where('token_hash', hash('sha256', $userToken))
            ->first();

        $this->assertNotNull($session);
        $this->assertNotNull($session->ended_at);
    }

    public function test_suspended_user_can_login_but_cannot_use_authenticated_actions(): void
    {
        [$admin, $token] = $this->createSuperAdminWithSession();
        $user = $this->createUser();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/suspend', [
            'reason' => 'Administrative review required.',
        ])->assertStatus(200);

        $login = $this->postJson('/api/login', [
            'phone' => '60222222222',
            'password' => 'Password1',
        ]);

        $login
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
            ]);

        $suspendedToken = $login->json('data.session.token');

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$suspendedToken
        )->getJson('/api/me');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is suspended.',
            ]);
    }

    public function test_regular_user_cannot_suspend_account(): void
    {
        $user = $this->createUser();

        $target = Account::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'name' => 'Target User',
            'phone' => '60333333333',
            'email' => 'target@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'regular-user-admin-token';

        $this->createSessionForAccount(
            $user,
            $token,
            'ffffffff-ffff-4fff-8fff-ffffffffffff'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$target->id.'/suspend');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_suspension_requires_session_token(): void
    {
        $user = $this->createUser();

        $response = $this->postJson(
            '/api/admin/accounts/'.$user->id.'/suspend'
        );

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_suspended_account_cannot_be_suspended_again(): void
    {
        [$admin, $token] = $this->createSuperAdminWithSession();
        $user = $this->createUser();

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/suspend', [
            'reason' => 'Administrative review required.',
        ])->assertStatus(200);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts/'.$user->id.'/suspend');

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Account is already suspended.',
            ]);
    }
}
