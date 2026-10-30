<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountAdministrationTest extends TestCase
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

    private function createIdentityVerification(
        Account $account,
        string $status,
        string $realName
    ): IdentityVerification {
        return IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => $realName,
            'mykad_number_encrypted' => Crypt::encryptString('900101145678'),
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }

    private function createSuperAdminWithSession(): array
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Super Admin',
            '60111111111',
            'admin@example.com',
            'SUPER_ADMIN'
        );

        $this->grantPermission($admin, 'accounts.suspend');

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
        return $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Test User',
            '60222222222',
            'user@example.com',
            'USER'
        );
    }

    // Account list tests

    public function test_root_super_admin_can_read_account_list(): void
    {
        $root = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $admin = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Super Admin',
            '60222222222',
            'admin@example.com',
            'SUPER_ADMIN'
        );

        $user = $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Test User',
            '60333333333',
            'user@example.com',
            'USER'
        );

        $token = 'account-list-root-token';

        $this->createSessionForAccount(
            $root,
            $token,
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts');

        $response->assertStatus(200);

        $response->assertJson([
            'success' => true,
        ]);

        $response->assertJsonCount(3, 'data.accounts');

        $response->assertJsonFragment([
            'id' => $admin->id,
            'role' => 'SUPER_ADMIN',
        ]);

        $response->assertJsonFragment([
            'id' => $user->id,
            'role' => 'USER',
        ]);
    }

    public function test_super_admin_can_read_user_accounts(): void
    {
        $admin = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Super Admin',
            '60111111111',
            'admin@example.com',
            'SUPER_ADMIN'
        );

        $this->grantPermission($admin, 'accounts.view');

        $user = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Test User',
            '60222222222',
            'user@example.com',
            'USER'
        );

        $token = 'account-list-admin-token';

        $this->createSessionForAccount(
            $admin,
            $token,
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts');

        $response->assertStatus(200);

        $response->assertJson([
            'success' => true,
        ]);

        $response->assertJsonCount(1, 'data.accounts');

        $response->assertJsonFragment([
            'id' => $user->id,
            'role' => 'USER',
        ]);

        $response->assertJsonMissing([
            'id' => $admin->id,
        ]);
    }

    public function test_account_list_shows_real_name_only_when_identity_is_verified(): void
    {
        $root = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $verifiedUser = $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Verified Display Name',
            '60222222222',
            'verified@example.com',
            'USER'
        );

        $pendingUser = $this->createAccount(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'Pending Display Name',
            '60333333333',
            'pending@example.com',
            'USER'
        );

        $this->createIdentityVerification(
            $verifiedUser,
            IdentityVerification::STATUS_VERIFIED,
            'Nama Sebenar Disahkan'
        );

        $this->createIdentityVerification(
            $pendingUser,
            IdentityVerification::STATUS_PENDING,
            'Nama Sebenar Belum Disahkan'
        );

        $token = 'account-list-real-name-token';

        $this->createSessionForAccount(
            $root,
            $token,
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts');

        $response->assertOk();

        $response->assertJsonFragment([
            'id' => $verifiedUser->id,
            'name' => 'Verified Display Name',
            'real_name' => 'Nama Sebenar Disahkan',
        ]);

        $response->assertJsonFragment([
            'id' => $pendingUser->id,
            'name' => 'Pending Display Name',
            'real_name' => null,
        ]);

        $response->assertJsonMissing([
            'real_name' => 'Nama Sebenar Belum Disahkan',
        ]);

        $response->assertJsonMissing([
            'mykad_number_encrypted' => '900101145678',
        ]);
    }

    public function test_regular_user_cannot_read_account_list(): void
    {
        $user = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Test User',
            '60111111111',
            'user@example.com',
            'USER'
        );

        $token = 'account-list-user-token';

        $this->createSessionForAccount(
            $user,
            $token,
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts');

        $response->assertStatus(403);
    }

    public function test_account_list_requires_session_token(): void
    {
        $response = $this->getJson('/api/admin/accounts');

        $response->assertStatus(401);
    }

    public function test_account_list_response_contains_safe_fields_only(): void
    {
        $root = $this->createAccount(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'Root Super Admin',
            '60111111111',
            'root@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $this->createAccount(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'Test User',
            '60222222222',
            'user@example.com',
            'USER'
        );

        $token = 'account-list-safe-fields-token';

        $this->createSessionForAccount(
            $root,
            $token,
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'success',
            'data' => [
                'accounts' => [
                    '*' => [
                        'id',
                        'name',
                        'real_name',
                        'phone',
                        'email',
                        'profile_picture',
                        'role',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ]);

        $response->assertJsonMissing([
            'password' => 'Password1',
        ]);

        $response->assertJsonMissing([
            'token_hash' => hash('sha256', $token),
        ]);
    }

    // Super admin account management tests

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

        $target = $this->createAccount(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'Target User',
            '60333333333',
            'target@example.com',
            'USER'
        );

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
                'message' => 'You do not have permission to perform this action.',
                'required_permission' => 'accounts.suspend',
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
