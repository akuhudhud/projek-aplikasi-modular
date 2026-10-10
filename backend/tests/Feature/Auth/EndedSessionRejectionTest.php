<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndedSessionRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ended_session_token_cannot_access_authenticated_endpoint(): void
    {
        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Ended Session Test',
            'phone' => '60111111111',
            'email' => 'ended-session@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'ended-session-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now()->subHour(),
            'ended_at' => now()->subMinute(),
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
        ]);
    }
}
