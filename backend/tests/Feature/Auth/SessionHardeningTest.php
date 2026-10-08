<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_request_updates_last_activity_at(): void
    {
        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Activity User',
            'email' => 'activity@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'activity-session-token';

        $originalActivityAt = now()->subMinute();

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now()->subHour(),
            'last_activity_at' => $originalActivityAt,
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/me');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $session = Session::findOrFail(
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
        );

        $this->assertNotNull($session->last_activity_at);
        $this->assertTrue(
            $session->last_activity_at->greaterThan($originalActivityAt)
        );

        $this->assertNull($session->ended_at);
        $this->assertNull($session->end_reason);
    }

    public function test_logout_sets_explicit_end_reason(): void
    {
        $account = Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'name' => 'Logout Reason User',
            'email' => 'logout-reason@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'logout-reason-session-token';

        Session::create([
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/logout');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful.',
            ]);

        $session = Session::findOrFail(
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        $this->assertNotNull($session->ended_at);
        $this->assertSame('LOGOUT', $session->end_reason);
    }
}
