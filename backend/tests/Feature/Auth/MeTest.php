<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_current_account_with_valid_session_token(): void
    {
        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Current User',
            'email' => 'current@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'current-session-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'account' => [
                        'id' => $account->id,
                        'name' => 'Current User',
                        'phone' => null,
                        'email' => 'current@example.com',
                        'profile_picture' => null,
                        'role' => 'USER',
                        'status' => 'ACTIVE',
                    ],
                ],
            ])
            ->assertJsonMissing([
                'password' => 'Password1',
            ])
            ->assertJsonMissing([
                'token_hash' => hash('sha256', $token),
            ]);
    }

    public function test_me_requires_session_token(): void
    {
        $response = $this->getJson('/api/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_me_rejects_invalid_session_token(): void
    {
        $response = $this->withHeader(
            'Authorization',
            'Bearer invalid-session-token'
        )->getJson('/api/me');

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_me_does_not_modify_account_or_session(): void
    {
        $account = Account::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'name' => 'Read Only User',
            'email' => 'readonly@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'readonly-session-token';

        $session = Session::create([
            'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me');

        $response->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Read Only User',
            'email' => 'readonly@example.com',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => $session->id,
            'account_id' => $account->id,
            'ended_at' => null,
        ]);
    }
}
