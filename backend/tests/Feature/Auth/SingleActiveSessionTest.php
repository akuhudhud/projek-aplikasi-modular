<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SingleActiveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_login_ends_previous_active_session(): void
    {
        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Single Session Test',
            'phone' => '60111111111',
            'email' => 'single-session@example.com',
            'password' => Hash::make('Password1'),
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $oldToken = 'previous-active-session-token';

        $oldSession = Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $oldToken),
            'created_at' => now()->subHour(),
            'last_activity_at' => now()->subHour(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->postJson('/api/login', [
            'phone' => $account->phone,
            'password' => 'Password1',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'account' => [
                        'id',
                        'name',
                        'phone',
                        'email',
                        'profile_picture',
                        'role',
                        'status',
                    ],
                    'session' => [
                        'token',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('sessions', [
            'id' => $oldSession->id,
            'account_id' => $account->id,
            'end_reason' => 'LOGIN_REPLACED',
        ]);

        $this->assertNotNull(
            Session::findOrFail($oldSession->id)->ended_at
        );

        $newToken = $response->json('data.session.token');

        $this->assertNotEmpty($newToken);
        $this->assertNotSame($oldToken, $newToken);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $newToken),
            'ended_at' => null,
        ]);

        $oldTokenResponse = $this->withHeader(
            'Authorization',
            'Bearer '.$oldToken
        )->getJson('/api/me');

        $oldTokenResponse->assertUnauthorized();

        $newTokenResponse = $this->withHeader(
            'Authorization',
            'Bearer '.$newToken
        )->getJson('/api/me');

        $newTokenResponse->assertOk();
    }
}
