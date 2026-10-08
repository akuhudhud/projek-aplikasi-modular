<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_login_replacement_sets_explicit_end_reason(): void
    {
        $account = Account::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'name' => 'Login Replacement User',
            'email' => 'login-replacement@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        Session::create([
            'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', 'old-login-token'),
            'created_at' => now()->subHour(),
            'last_activity_at' => now()->subMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login-replacement@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $oldSession = Session::findOrFail(
            'ffffffff-ffff-4fff-8fff-ffffffffffff'
        );

        $this->assertNotNull($oldSession->ended_at);
        $this->assertSame(
            'LOGIN_REPLACED',
            $oldSession->end_reason
        );

        $activeSessions = Session::query()
            ->where('account_id', $account->id)
            ->whereNull('ended_at')
            ->get();

        $this->assertCount(1, $activeSessions);
        $this->assertNull($activeSessions->first()->end_reason);
        $this->assertNotNull($activeSessions->first()->last_activity_at);
    }

    public function test_password_change_sets_explicit_end_reason(): void
    {
        $account = Account::create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Password Reason User',
            'email' => 'password-reason@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'password-reason-session-token';

        Session::create([
            'id' => '22222222-2222-4222-8222-222222222222',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now()->subHour(),
            'last_activity_at' => now()->subMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/change-password', [
            'current_password' => 'Password1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully. Please login again.',
            ]);

        $session = Session::findOrFail(
            '22222222-2222-4222-8222-222222222222'
        );

        $this->assertNotNull($session->ended_at);
        $this->assertSame(
            'PASSWORD_CHANGED',
            $session->end_reason
        );

        $this->assertTrue(
            Hash::check(
                'NewPassword2',
                $account->fresh()->password
            )
        );
    }

    public function test_deactivation_sets_explicit_end_reason(): void
    {
        $account = Account::create([
            'id' => '33333333-3333-4333-8333-333333333333',
            'name' => 'Deactivation Reason User',
            'email' => 'deactivation-reason@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'deactivation-reason-token';

        Session::create([
            'id' => '44444444-4444-4444-8444-444444444444',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now()->subHour(),
            'last_activity_at' => now()->subMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/deactivate');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deactivated successfully.',
            ]);

        $session = Session::findOrFail(
            '44444444-4444-4444-8444-444444444444'
        );

        $this->assertNotNull($session->ended_at);
        $this->assertSame(
            'ACCOUNT_DEACTIVATED',
            $session->end_reason
        );
    }

    public function test_suspension_sets_explicit_end_reason(): void
    {
        $admin = Account::create([
            'id' => '55555555-5555-4555-8555-555555555555',
            'name' => 'Session Admin',
            'email' => 'session-admin@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $adminToken = 'session-admin-token';

        Session::create([
            'id' => '66666666-6666-4666-8666-666666666666',
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $adminToken),
            'created_at' => now(),
            'last_activity_at' => now()->subMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $account = Account::create([
            'id' => '77777777-7777-4777-8777-777777777777',
            'name' => 'Suspension Reason User',
            'email' => 'suspension-reason@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $userToken = 'suspension-reason-token';

        Session::create([
            'id' => '88888888-8888-4888-8888-888888888888',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $userToken),
            'created_at' => now()->subHour(),
            'last_activity_at' => now()->subMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$adminToken
        )->postJson(
            '/api/admin/accounts/'.$account->id.'/suspend',
            [
                'reason' => 'Security review required.',
            ]
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account suspended successfully.',
            ]);

        $session = Session::findOrFail(
            '88888888-8888-4888-8888-888888888888'
        );

        $this->assertNotNull($session->ended_at);
        $this->assertSame(
            'ACCOUNT_SUSPENDED',
            $session->end_reason
        );
    }

    public function test_deletion_sets_explicit_end_reason(): void
    {
        $account = Account::create([
            'id' => '99999999-9999-4999-8999-999999999999',
            'name' => 'Deletion Reason User',
            'email' => 'deletion-reason@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'deletion-reason-token';

        Session::create([
            'id' => 'aaaaaaaa-bbbb-4aaa-8bbb-aaaaaaaaaaaa',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now()->subHour(),
            'last_activity_at' => now()->subMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/me/delete');

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);

        $session = Session::findOrFail(
            'aaaaaaaa-bbbb-4aaa-8bbb-aaaaaaaaaaaa'
        );

        $this->assertNotNull($session->ended_at);
        $this->assertSame(
            'ACCOUNT_DELETED',
            $session->end_reason
        );
    }

    public function test_login_replacement_ends_all_existing_active_sessions(): void
    {
        $account = Account::create([
            'id' => 'bbbbbbbb-cccc-4bbb-8ccc-bbbbbbbbbbbb',
            'name' => 'Multiple Session User',
            'email' => 'multiple-session@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        Session::create([
            'id' => 'cccccccc-dddd-4ccc-8ddd-cccccccccccc',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', 'first-old-token'),
            'created_at' => now()->subHours(2),
            'last_activity_at' => now()->subHour(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        Session::create([
            'id' => 'dddddddd-eeee-4ddd-8eee-dddddddddddd',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', 'second-old-token'),
            'created_at' => now()->subHour(),
            'last_activity_at' => now()->subMinutes(30),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'multiple-session@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $oldSessions = Session::query()
            ->where('account_id', $account->id)
            ->whereIn('id', [
                'cccccccc-dddd-4ccc-8ddd-cccccccccccc',
                'dddddddd-eeee-4ddd-8eee-dddddddddddd',
            ])
            ->get();

        $this->assertCount(2, $oldSessions);

        foreach ($oldSessions as $oldSession) {
            $this->assertNotNull($oldSession->ended_at);
            $this->assertSame(
                'LOGIN_REPLACED',
                $oldSession->end_reason
            );
        }

        $activeSessions = Session::query()
            ->where('account_id', $account->id)
            ->whereNull('ended_at')
            ->get();

        $this->assertCount(1, $activeSessions);

        $newSession = $activeSessions->first();

        $this->assertNotNull($newSession->last_activity_at);
        $this->assertNull($newSession->end_reason);
        $this->assertSame(
            $response->json('data.session.token')
                ? hash(
                    'sha256',
                    $response->json('data.session.token')
                )
                : null,
            $newSession->token_hash
        );
    }
}
