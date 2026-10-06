<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountListTest extends TestCase
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

        $response->assertJsonCount(
            3,
            'data.accounts'
        );

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

        $response->assertJsonCount(
            1,
            'data.accounts'
        );

        $response->assertJsonFragment([
            'id' => $user->id,
            'role' => 'USER',
        ]);

        $response->assertJsonMissing([
            'id' => $admin->id,
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

        $user = $this->createAccount(
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
}
