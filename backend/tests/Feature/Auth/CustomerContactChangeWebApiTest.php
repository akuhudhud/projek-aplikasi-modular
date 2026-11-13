<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerContactChangeWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): array
    {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Contact Change Customer',
            'phone' => '60123456789',
            'email' => 'contact-customer@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

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

        return [$account, $token];
    }

    private function authenticatedSession(string $token): static
    {
        return $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    public function test_customer_can_change_phone_through_web_session(): void
    {
        [$account, $token] = $this->createCustomer();

        $request = $this->authenticatedSession($token)
            ->postJson('/web/auth/change-phone/request', [
                'phone' => '60198765432',
            ]);

        $request->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'verification_id',
                    'expires_at',
                ],
            ]);

        $verificationId = $request->json('data.verification_id');

        $verify = $this->authenticatedSession($token)
            ->postJson('/web/auth/change-phone/verify', [
                'verification_id' => $verificationId,
                'otp' => '123456',
            ]);

        $verify->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'verification_token',
                    'expires_at',
                ],
            ]);

        $verificationToken = $verify->json(
            'data.verification_token'
        );

        $complete = $this->authenticatedSession($token)
            ->postJson('/web/auth/change-phone/complete', [
                'verification_token' => $verificationToken,
            ]);

        $complete->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.account.phone',
                '60198765432'
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'phone' => '60198765432',
        ]);

        $this->assertNotNull(
            $account->fresh()->phone_verified_at
        );
    }

    public function test_customer_can_change_email_through_web_session(): void
    {
        [$account, $token] = $this->createCustomer();

        $request = $this->authenticatedSession($token)
            ->postJson('/web/auth/change-email/request', [
                'email' => 'new-contact@example.com',
            ]);

        $request->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'verification_id',
                    'expires_at',
                ],
            ]);

        $verificationId = $request->json('data.verification_id');

        $verify = $this->authenticatedSession($token)
            ->postJson('/web/auth/change-email/verify', [
                'verification_id' => $verificationId,
                'otp' => '123456',
            ]);

        $verify->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'verification_token',
                    'expires_at',
                ],
            ]);

        $verificationToken = $verify->json(
            'data.verification_token'
        );

        $complete = $this->authenticatedSession($token)
            ->postJson('/web/auth/change-email/complete', [
                'verification_token' => $verificationToken,
            ]);

        $complete->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.account.email',
                'new-contact@example.com'
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'email' => 'new-contact@example.com',
        ]);

        $this->assertNotNull(
            $account->fresh()->email_verified_at
        );
    }

    public function test_phone_change_web_routes_require_authentication(): void
    {
        $payloads = [
            '/web/auth/change-phone/request' => [
                'phone' => '60198765432',
            ],
            '/web/auth/change-phone/verify' => [
                'verification_id' => (string) Str::uuid(),
                'otp' => '123456',
            ],
            '/web/auth/change-phone/complete' => [
                'verification_token' => Str::random(64),
            ],
        ];

        foreach ($payloads as $uri => $payload) {
            $this->postJson($uri, $payload)
                ->assertUnauthorized();
        }
    }

    public function test_email_change_web_routes_require_authentication(): void
    {
        $payloads = [
            '/web/auth/change-email/request' => [
                'email' => 'new-contact@example.com',
            ],
            '/web/auth/change-email/verify' => [
                'verification_id' => (string) Str::uuid(),
                'otp' => '123456',
            ],
            '/web/auth/change-email/complete' => [
                'verification_token' => Str::random(64),
            ],
        ];

        foreach ($payloads as $uri => $payload) {
            $this->postJson($uri, $payload)
                ->assertUnauthorized();
        }
    }
}
