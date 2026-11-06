<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\RegistrationVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationWebApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_registration_otp(): void
    {
        $response = $this->postJson(
            '/web/auth/register/otp/request',
            [
                'channel' => 'phone',
                'contact' => '60123456789',
                'purpose' => 'REGISTRATION',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'verification_id',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseHas('registration_verifications', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'account_id' => null,
        ]);
    }

    public function test_registration_otp_request_rejects_existing_contact(): void
    {
        Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Existing Customer',
            'phone' => '60123456789',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $this->postJson(
            '/web/auth/register/otp/request',
            [
                'channel' => 'phone',
                'contact' => '60123456789',
                'purpose' => 'REGISTRATION',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_registration_otp_request_rejects_non_registration_purpose(): void
    {
        $this->postJson(
            '/web/auth/register/otp/request',
            [
                'channel' => 'phone',
                'contact' => '60123456789',
                'purpose' => 'CHANGE_PHONE',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_customer_can_verify_registration_otp(): void
    {
        $request = $this->postJson(
            '/web/auth/register/otp/request',
            [
                'channel' => 'phone',
                'contact' => '60123456789',
                'purpose' => 'REGISTRATION',
            ]
        );

        $verificationId = $request->json(
            'data.verification_id'
        );

        $this->postJson(
            '/web/auth/register/otp/verify',
            [
                'verification_id' => $verificationId,
                'otp' => '123456',
            ]
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'verification_token',
                    'expires_at',
                ],
            ]);
    }

    public function test_registration_otp_verify_rejects_invalid_otp(): void
    {
        $request = $this->postJson(
            '/web/auth/register/otp/request',
            [
                'channel' => 'phone',
                'contact' => '60123456789',
                'purpose' => 'REGISTRATION',
            ]
        );

        $this->postJson(
            '/web/auth/register/otp/verify',
            [
                'verification_id' => $request->json(
                    'data.verification_id'
                ),
                'otp' => '000000',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_complete_registration_and_receive_web_session(): void
    {
        $request = $this->postJson(
            '/web/auth/register/otp/request',
            [
                'channel' => 'phone',
                'contact' => '60123456789',
                'purpose' => 'REGISTRATION',
            ]
        );

        $verificationId = $request->json(
            'data.verification_id'
        );

        $verify = $this->postJson(
            '/web/auth/register/otp/verify',
            [
                'verification_id' => $verificationId,
                'otp' => '123456',
            ]
        );

        $verificationToken = $verify->json(
            'data.verification_token'
        );

        $response = $this->postJson(
            '/web/auth/register/complete',
            [
                'verification_token' => $verificationToken,
                'name' => 'New Customer',
                'password' => 'Password1',
                'password_confirmation' => 'Password1',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.account.name',
                'New Customer'
            )
            ->assertJsonPath(
                'data.account.phone',
                '60123456789'
            )
            ->assertJsonPath(
                'data.account.role',
                'USER'
            )
            ->assertJsonPath(
                'data.account.status',
                'ACTIVE'
            )
            ->assertJsonMissingPath('data.session.token');

        $account = Account::query()
            ->where('phone', '60123456789')
            ->firstOrFail();

        $this->assertNotNull($account->phone_verified_at);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'ended_at' => null,
        ]);

        $sessionToken = $this->app['session.store']->get(
            AuthenticateWebSession::SESSION_TOKEN_KEY
        );

        $this->assertIsString($sessionToken);
        $this->assertNotSame('', $sessionToken);

        $this->assertDatabaseHas('sessions', [
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $sessionToken),
            'ended_at' => null,
        ]);
    }

    public function test_registration_verification_token_cannot_be_reused(): void
    {
        $request = $this->postJson(
            '/web/auth/register/otp/request',
            [
                'channel' => 'email',
                'contact' => 'newcustomer@example.com',
                'purpose' => 'REGISTRATION',
            ]
        );

        $verificationId = $request->json(
            'data.verification_id'
        );

        $verify = $this->postJson(
            '/web/auth/register/otp/verify',
            [
                'verification_id' => $verificationId,
                'otp' => '123456',
            ]
        );

        $verificationToken = $verify->json(
            'data.verification_token'
        );

        $payload = [
            'verification_token' => $verificationToken,
            'name' => 'New Customer',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ];

        $this->postJson(
            '/web/auth/register/complete',
            $payload
        )->assertCreated();

        $this->postJson(
            '/web/auth/register/complete',
            $payload
        )->assertStatus(422);

        $this->assertDatabaseCount('accounts', 1);
    }

    public function test_registration_complete_rejects_invalid_verification_token(): void
    {
        $this->postJson(
            '/web/auth/register/complete',
            [
                'verification_token' => str_repeat('x', 64),
                'name' => 'New Customer',
                'password' => 'Password1',
                'password_confirmation' => 'Password1',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_registration_creates_verified_email_account(): void
    {
        $request = $this->postJson(
            '/web/auth/register/otp/request',
            [
                'channel' => 'email',
                'contact' => 'emailcustomer@example.com',
                'purpose' => 'REGISTRATION',
            ]
        );

        $verify = $this->postJson(
            '/web/auth/register/otp/verify',
            [
                'verification_id' => $request->json(
                    'data.verification_id'
                ),
                'otp' => '123456',
            ]
        );

        $this->postJson(
            '/web/auth/register/complete',
            [
                'verification_token' => $verify->json(
                    'data.verification_token'
                ),
                'name' => 'Email Customer',
                'password' => 'Password1',
                'password_confirmation' => 'Password1',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.account.email',
                'emailcustomer@example.com'
            );

        $account = Account::query()
            ->where('email', 'emailcustomer@example.com')
            ->firstOrFail();

        $this->assertNotNull($account->email_verified_at);
        $this->assertNull($account->phone);
    }
}
