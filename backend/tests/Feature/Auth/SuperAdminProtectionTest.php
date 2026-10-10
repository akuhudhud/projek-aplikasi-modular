<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_super_admin_cannot_suspend_another_root_super_admin(): void
    {
        [$root, $token] = $this->createAccountWithSession(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            '60111111111',
            'root-one@example.com',
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'ROOT_SUPER_ADMIN'
        );

        $target = Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'name' => 'Second Root Super Admin',
            'phone' => '60222222222',
            'email' => 'root-two@example.com',
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$target->id.'/suspend',
            [
                'reason' => 'Protection test.',
            ]
        );

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_super_admin_cannot_suspend_another_super_admin(): void
    {
        [, $token] = $this->createAccountWithSession(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            '60333333333',
            'super-admin-one@example.com',
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'SUPER_ADMIN'
        );

        $target = Account::create([
            'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'name' => 'Second Super Admin',
            'phone' => '60444444444',
            'email' => 'super-admin-two@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$target->id.'/suspend',
            [
                'reason' => 'Protection test.',
            ]
        );

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

    public function test_super_admin_cannot_create_another_super_admin(): void
    {
        [, $token] = $this->createAccountWithSession(
            '11111111-1111-4111-8111-111111111111',
            '60555555555',
            'super-admin-create@example.com',
            '22222222-2222-4222-8222-222222222222',
            'SUPER_ADMIN'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'Unauthorized Super Admin',
            'phone' => '60666666666',
            'email' => 'unauthorized-admin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Protection test.',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'unauthorized-admin@example.com',
        ]);
    }

    private function createAccountWithSession(
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
}
