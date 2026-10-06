<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactivateAccountTest extends TestCase
{
    use RefreshDatabase;

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
                'message' => 'Unauthorized.',
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
}
