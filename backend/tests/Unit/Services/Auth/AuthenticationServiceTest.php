<?php

namespace Tests\Unit\Services\Auth;

use App\Models\Account;
use App\Models\Session;
use App\Services\Auth\AuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthenticationService $authenticationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticationService = app(
            AuthenticationService::class
        );
    }

    public function test_account_can_authenticate_with_phone(): void
    {
        $account = $this->createAccount([
            'phone' => '60123456789',
        ]);

        $result = $this->authenticationService->authenticate(
            '60123456789',
            null,
            'Password1'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['status']);
        $this->assertSame($account->id, $result['account']->id);
        $this->assertArrayHasKey('session', $result);
        $this->assertNotEmpty($result['session']['token']);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'ended_at' => null,
        ]);
    }

    public function test_account_can_authenticate_with_email(): void
    {
        $account = $this->createAccount([
            'email' => 'auth@example.com',
        ]);

        $result = $this->authenticationService->authenticate(
            null,
            'auth@example.com',
            'Password1'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['status']);
        $this->assertSame($account->id, $result['account']->id);
        $this->assertNotEmpty($result['session']['token']);
    }

    public function test_invalid_password_is_rejected_and_attempt_is_recorded(): void
    {
        $account = $this->createAccount([
            'email' => 'wrong-password@example.com',
        ]);

        $result = $this->authenticationService->authenticate(
            null,
            'wrong-password@example.com',
            'WrongPass1'
        );

        $this->assertFalse($result['success']);
        $this->assertSame(401, $result['status']);
        $this->assertSame(
            'Invalid credentials.',
            $result['message']
        );

        $this->assertSame(
            1,
            $account->fresh()->failed_login_attempts
        );

        $this->assertDatabaseMissing('sessions', [
            'account_id' => $account->id,
        ]);
    }

    public function test_unknown_account_is_rejected(): void
    {
        $result = $this->authenticationService->authenticate(
            null,
            'unknown@example.com',
            'Password1'
        );

        $this->assertFalse($result['success']);
        $this->assertSame(401, $result['status']);
        $this->assertSame(
            'Invalid credentials.',
            $result['message']
        );
    }

    public function test_fifth_failed_attempt_locks_account_for_thirty_minutes(): void
    {
        $account = $this->createAccount([
            'email' => 'lock-test@example.com',
        ]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $result = $this->authenticationService->authenticate(
                null,
                'lock-test@example.com',
                'WrongPass1'
            );

            $this->assertSame(401, $result['status']);
        }

        $result = $this->authenticationService->authenticate(
            null,
            'lock-test@example.com',
            'WrongPass1'
        );

        $this->assertFalse($result['success']);
        $this->assertSame(423, $result['status']);

        $account->refresh();

        $this->assertSame(5, $account->failed_login_attempts);
        $this->assertNotNull($account->locked_until);
        $this->assertTrue($account->locked_until->isFuture());
        $this->assertTrue(
            $account->locked_until->lte(now()->addMinutes(30))
        );
    }

    public function test_account_with_active_lock_cannot_authenticate(): void
    {
        $account = $this->createAccount([
            'email' => 'active-lock@example.com',
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(10),
        ]);

        $result = $this->authenticationService->authenticate(
            null,
            'active-lock@example.com',
            'Password1'
        );

        $this->assertFalse($result['success']);
        $this->assertSame(423, $result['status']);

        $this->assertSame(
            5,
            $account->fresh()->failed_login_attempts
        );

        $this->assertDatabaseMissing('sessions', [
            'account_id' => $account->id,
        ]);
    }

    public function test_expired_lock_is_reset_before_authentication(): void
    {
        $account = $this->createAccount([
            'email' => 'expired-lock@example.com',
            'failed_login_attempts' => 5,
            'locked_until' => now()->subMinute(),
        ]);

        $result = $this->authenticationService->authenticate(
            null,
            'expired-lock@example.com',
            'Password1'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['status']);

        $account->refresh();

        $this->assertSame(0, $account->failed_login_attempts);
        $this->assertNull($account->locked_until);
    }

    public function test_deactivated_account_cannot_authenticate(): void
    {
        $account = $this->createAccount([
            'email' => 'deactivated@example.com',
            'status' => 'DEACTIVATED',
        ]);

        $result = $this->authenticationService->authenticate(
            null,
            'deactivated@example.com',
            'Password1'
        );

        $this->assertFalse($result['success']);
        $this->assertSame(403, $result['status']);

        $this->assertDatabaseMissing('sessions', [
            'account_id' => $account->id,
        ]);
    }

    public function test_deleted_account_cannot_authenticate(): void
    {
        $account = $this->createAccount([
            'email' => 'deleted@example.com',
            'status' => 'DELETED',
        ]);

        $result = $this->authenticationService->authenticate(
            null,
            'deleted@example.com',
            'Password1'
        );

        $this->assertFalse($result['success']);
        $this->assertSame(403, $result['status']);

        $this->assertDatabaseMissing('sessions', [
            'account_id' => $account->id,
        ]);
    }

    public function test_suspended_account_can_authenticate(): void
    {
        $account = $this->createAccount([
            'email' => 'suspended@example.com',
            'status' => 'SUSPENDED',
        ]);

        $result = $this->authenticationService->authenticate(
            null,
            'suspended@example.com',
            'Password1'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['status']);
        $this->assertSame(
            'SUSPENDED',
            $result['account']->status
        );

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'ended_at' => null,
        ]);
    }

    public function test_successful_authentication_resets_failed_attempts(): void
    {
        $account = $this->createAccount([
            'email' => 'reset-attempts@example.com',
            'failed_login_attempts' => 3,
        ]);

        $result = $this->authenticationService->authenticate(
            null,
            'reset-attempts@example.com',
            'Password1'
        );

        $this->assertTrue($result['success']);

        $account->refresh();

        $this->assertSame(0, $account->failed_login_attempts);
        $this->assertNull($account->locked_until);
    }

    public function test_successful_authentication_replaces_existing_active_sessions(): void
    {
        $account = $this->createAccount([
            'email' => 'replace-session@example.com',
        ]);

        $oldSession = Session::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', 'old-session-token'),
            'created_at' => now()->subHour(),
            'last_activity_at' => now()->subMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $result = $this->authenticationService->authenticate(
            null,
            'replace-session@example.com',
            'Password1'
        );

        $this->assertTrue($result['success']);

        $oldSession->refresh();

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

        $this->assertSame(
            hash('sha256', $result['session']['token']),
            $activeSessions->first()->token_hash
        );
    }

    private function createAccount(array $attributes = []): Account
    {
        return Account::create(array_merge([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'profile_name' => 'Authentication Test User',
            'email' => 'authentication-test@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ], $attributes));
    }
}
