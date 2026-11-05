<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $status = 'ACTIVE',
        ?string $phone = null,
        ?string $email = null
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Web Customer',
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => $status,
        ]);
    }

    public function test_customer_can_login_with_phone_using_web_session(): void
    {
        $account = $this->createAccount(
            'ACTIVE',
            '60123456789'
        );

        $response = $this->postJson('/web/auth/login', [
            'phone' => '60123456789',
            'password' => 'Password1',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.id', $account->id)
            ->assertJsonMissingPath('data.session.token');

        $token = $this->app['session.store']->get(
            AuthenticateWebSession::SESSION_TOKEN_KEY
        );

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'ended_at' => null,
        ]);
    }

    public function test_customer_can_login_with_email_using_web_session(): void
    {
        $account = $this->createAccount(
            'ACTIVE',
            null,
            'customer@example.com'
        );

        $response = $this->postJson('/web/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'Password1',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.id', $account->id)
            ->assertJsonMissingPath('data.session.token');

        $token = $this->app['session.store']->get(
            AuthenticateWebSession::SESSION_TOKEN_KEY
        );

        $this->assertIsString($token);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'ended_at' => null,
        ]);
    }

    public function test_authenticated_customer_can_access_me_endpoint(): void
    {
        $account = $this->createAccount(
            'ACTIVE',
            null,
            'me@example.com'
        );

        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now()->subMinute(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ])->getJson('/web/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.id', $account->id)
            ->assertJsonPath('data.account.email', 'me@example.com');
    }

    public function test_customer_can_logout_and_session_is_ended(): void
    {
        $account = $this->createAccount(
            'ACTIVE',
            null,
            'logout@example.com'
        );

        $token = Str::random(64);

        $session = Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $response = $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ])->postJson('/web/auth/logout');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful.',
            ]);

        $this->assertNotNull($session->fresh()->ended_at);
        $this->assertSame(
            'LOGOUT',
            $session->fresh()->end_reason
        );

        $this->getJson('/web/auth/me')
            ->assertUnauthorized();
    }

    public function test_revoked_web_session_cannot_access_me_endpoint(): void
    {
        $account = $this->createAccount(
            'ACTIVE',
            null,
            'revoked@example.com'
        );

        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => now(),
            'end_reason' => 'SECURITY_INVALIDATED',
        ]);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ])->getJson('/web/auth/me')
            ->assertUnauthorized();
    }

    public function test_request_without_web_session_is_unauthorized(): void
    {
        $this->getJson('/web/auth/me')
            ->assertUnauthorized();

        $this->postJson('/web/auth/logout')
            ->assertUnauthorized();
    }

    public function test_suspended_account_cannot_use_web_session(): void
    {
        $account = $this->createAccount(
            'SUSPENDED',
            null,
            'suspended-web@example.com'
        );

        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ])->getJson('/web/auth/me')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Account is suspended.',
            ]);
    }

    public function test_deactivated_account_cannot_use_web_session(): void
    {
        $account = $this->createAccount(
            'DEACTIVATED',
            null,
            'deactivated-web@example.com'
        );

        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ])->getJson('/web/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Account is not available.',
            ]);
    }

    public function test_deleted_account_cannot_use_web_session(): void
    {
        $account = $this->createAccount(
            'DELETED',
            null,
            'deleted-web@example.com'
        );

        $token = Str::random(64);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ])->getJson('/web/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Account is not available.',
            ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->createAccount(
            'ACTIVE',
            null,
            'invalid-web@example.com'
        );

        $this->postJson('/web/auth/login', [
            'email' => 'invalid-web@example.com',
            'password' => 'WrongPass1',
        ])
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials.',
            ]);
    }
}
