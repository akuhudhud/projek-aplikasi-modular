<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEndpointAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_list_admin_accounts(): void
    {
        [$user, $token] = $this->createAuthenticatedUser(
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            '60111111111',
            'regular-admin-list@example.com',
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts');

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_regular_user_cannot_create_super_admin(): void
    {
        [, $token] = $this->createAuthenticatedUser(
            'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            '60222222222',
            'regular-admin-create@example.com',
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'Unauthorized Super Admin',
            'phone' => '60333333333',
            'email' => 'unauthorized-super-admin@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Authorization test.',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);

        $this->assertDatabaseMissing('accounts', [
            'email' => 'unauthorized-super-admin@example.com',
        ]);
    }

    public function test_regular_user_cannot_suspend_another_account(): void
    {
        [, $token] = $this->createAuthenticatedUser(
            'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            '60444444444',
            'regular-admin-suspend@example.com',
            'ffffffff-ffff-4fff-8fff-ffffffffffff'
        );

        $target = Account::create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Suspension Target',
            'phone' => '60555555555',
            'email' => 'suspension-target@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$target->id.'/suspend',
            [
                'reason' => 'Authorization test.',
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
            'status' => 'ACTIVE',
        ]);
    }

    private function createAuthenticatedUser(
        string $accountId,
        string $phone,
        string $email,
        string $sessionId
    ): array {
        $user = Account::create([
            'id' => $accountId,
            'name' => 'Regular User',
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'admin-endpoint-token-'.$accountId;

        Session::create([
            'id' => $sessionId,
            'account_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$user, $token];
    }
}
