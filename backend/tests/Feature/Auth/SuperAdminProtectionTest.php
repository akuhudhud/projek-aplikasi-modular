<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_super_admin_cannot_suspend_own_account(): void
    {
        [$root, $token] = $this->createAccountWithSession(
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
            'id' => $root->id,
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
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
