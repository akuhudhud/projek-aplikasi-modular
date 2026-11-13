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

    private function createAuthenticatedCustomer(): array
    {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Contact Customer',
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

    private function authenticatedRequest(string $token)
    {
        return $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    public function test_customer_can_change_phone_through_web_session(): void
    {
        [$account, $token] = $this->createAuthenticatedCustomer();

        $request = $this->authenticatedRequest($token)
            ->postJson('/web/auth/change-phone/request', [
                'phone' => '60199999999',
            ]);

        $request->assertOk()
            ->assertJsonPath('success', true);

        $verificationId = $request->json('data.verification_id');

        $this->assertNotEmpty($verificationId);

        $verify = $this->authenticatedRequest($token)
            ->postJson('/web/auth/change-phone/verify', [
                'verification_id' => $verificationId,
                'otp' => '123456',
            ]);

        $verify->assertOk()
            ->assertJsonPath('success', true);

        $verificationToken = $verify->json(
            'data.verification_token'
        );

        $this->assertNotEmpty($verificationToken);

        $complete = $this->authenticatedRequest($token)
            ->postJson('/web/auth/change-phone/complete', [
                'verification_token' => $verificationToken,
            ]);

        $complete->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'phone' => '60199999999',
        ]);

        $this->assertNotNull(
            $account->fresh()->phone_verified_at
        );
    }

    public function test_customer_can_change_email_through_web_session(): void
    {
        [$account, $token] = $this->createAuthenticatedCustomer();

        $request = $this->authenticatedRequest($token)
            ->postJson('/web/auth/change-email/request', [
                'email' => 'new-contact@example.com',
            ]);

        $request->assertOk()
            ->assertJsonPath('success', true);

        $verificationId = $request->json('data.verification_id');

        $this->assertNotEmpty($verificationId);

        $verify = $this->authenticatedRequest($token)
            ->postJson('/web/auth/change-email/verify', [
                'verification_id' => $verificationId,
                'otp' => '123456',
            ]);

        $verify->assertOk()
            ->assertJsonPath('success', true);

        $verificationToken = $verify->json(
            'data.verification_token'
        );

        $this->assertNotEmpty($verificationToken);

        $complete = $this->authenticatedRequest($token)
            ->postJson('/web/auth/change-email/complete', [
                'verification_token' => $verificationToken,
            ]);

        $complete->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'email' => 'new-contact@example.com',
        ]);

        $this->assertNotNull(
            $account->fresh()->email_verified_at
        );
    }

    public function test_contact_change_web_routes_require_authentication(): void
    {
        $this->postJson('/web/auth/change-phone/request', [
            'phone' => '60199999999',
        ])->assertUnauthorized();

        $this->postJson('/web/auth/change-email/request', [
            'email' => 'new-contact@example.com',
        ])->assertUnauthorized();

        $this->postJson('/web/auth/change-phone/complete', [
            'verification_token' => str_repeat('a', 64),
        ])->assertUnauthorized();

        $this->postJson('/web/auth/change-email/complete', [
            'verification_token' => str_repeat('a', 64),
        ])->assertUnauthorized();
    }
}
