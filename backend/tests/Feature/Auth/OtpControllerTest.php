<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OtpControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSession(
        string $accountId,
        string $token
    ): Account {
        $account = Account::create([
            'id' => $accountId,
            'name' => 'Test User',
            'phone' => '601'.$accountId,
            'email' => $accountId.'@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        Session::create([
            'id' => str_replace('-', '', $accountId).'00000000',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $account;
    }

    public function test_otp_request_returns_verification_id(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'OTP sent successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'verification_id',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseCount(
            'registration_verifications',
            1
        );

        $this->assertDatabaseHas(
            'registration_verifications',
            [
                'account_id' => null,
                'purpose' => 'REGISTRATION',
                'resend_count' => 0,
            ]
        );
    }

    public function test_otp_request_returns_429_during_resend_cooldown(): void
    {
        $payload = [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ];

        Carbon::setTestNow('2026-10-07 23:00:00');

        $this->postJson(
            '/api/otp/request',
            $payload
        )->assertOk();

        Carbon::setTestNow('2026-10-07 23:00:30');

        $this->postJson(
            '/api/otp/request',
            $payload
        )
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Please wait before requesting another OTP.',
                'retry_after_seconds' => 30,
            ]);

        Carbon::setTestNow();
    }

    public function test_otp_request_returns_429_after_resend_limit(): void
    {
        $payload = [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ];

        Carbon::setTestNow('2026-10-07 23:00:00');

        $this->postJson(
            '/api/otp/request',
            $payload
        )->assertOk();

        for ($resend = 1; $resend <= 5; $resend++) {
            Carbon::setTestNow(
                '2026-10-07 23:'.str_pad(
                    (string) $resend,
                    2,
                    '0',
                    STR_PAD_LEFT
                ).':00'
            );

            $this->postJson(
                '/api/otp/request',
                $payload
            )->assertOk();
        }

        Carbon::setTestNow('2026-10-07 23:06:00');

        $this->postJson(
            '/api/otp/request',
            $payload
        )
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'OTP resend limit reached. Please start a new verification request.',
            ]);

        Carbon::setTestNow();
    }

    public function test_otp_request_rejects_invalid_channel(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'whatsapp',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $response->assertStatus(422);
    }

    public function test_otp_request_rejects_invalid_purpose(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'LOGIN',
        ]);

        $response->assertStatus(422);
    }

    public function test_otp_request_binds_change_phone_to_authenticated_account(): void
    {
        $accountId = '11111111-1111-4111-8111-111111111111';
        $token = 'otp-request-account-token';

        $this->createAccountWithSession(
            $accountId,
            $token
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60199999999',
            'purpose' => 'CHANGE_PHONE',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas(
            'registration_verifications',
            [
                'account_id' => $accountId,
                'purpose' => 'CHANGE_PHONE',
                'contact' => '60199999999',
            ]
        );
    }

    public function test_change_phone_otp_request_requires_authentication(): void
    {
        $response = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60199999999',
            'purpose' => 'CHANGE_PHONE',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_otp_verify_returns_verification_token(): void
    {
        $request = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $verificationId = $request->json(
            'data.verification_id'
        );

        $response = $this->postJson('/api/otp/verify', [
            'verification_id' => $verificationId,
            'otp' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'OTP verified successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'verification_token',
                    'expires_at',
                ],
            ]);
    }

    public function test_otp_verify_rejects_invalid_otp(): void
    {
        $request = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $verificationId = $request->json(
            'data.verification_id'
        );

        $response = $this->postJson('/api/otp/verify', [
            'verification_id' => $verificationId,
            'otp' => '000000',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ]);
    }

    public function test_contact_change_otp_verification_requires_authentication(): void
    {
        $request = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60199999999',
            'purpose' => 'CHANGE_PHONE',
        ]);

        $request->assertStatus(401);
    }

    public function test_contact_change_otp_cannot_be_verified_by_another_account(): void
    {
        $ownerId = '22222222-2222-4222-8222-222222222222';
        $ownerToken = 'otp-owner-token';

        $otherId = '33333333-3333-4333-8333-333333333333';
        $otherToken = 'otp-other-token';

        $this->createAccountWithSession(
            $ownerId,
            $ownerToken
        );

        $this->createAccountWithSession(
            $otherId,
            $otherToken
        );

        $request = $this->withHeaders([
            'Authorization' => 'Bearer '.$ownerToken,
            'Accept' => 'application/json',
        ])->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60199999999',
            'purpose' => 'CHANGE_PHONE',
        ]);

        $request->assertOk();

        $verificationId = $request->json(
            'data.verification_id'
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$otherToken,
            'Accept' => 'application/json',
        ])->postJson('/api/otp/verify', [
            'verification_id' => $verificationId,
            'otp' => '123456',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Verification request does not belong to the authenticated account.',
            ]);
    }

    public function test_contact_change_otp_can_be_verified_by_owning_account(): void
    {
        $accountId = '44444444-4444-4444-8444-444444444444';
        $token = 'otp-own-account-token';

        $this->createAccountWithSession(
            $accountId,
            $token
        );

        $request = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60188888888',
            'purpose' => 'CHANGE_PHONE',
        ]);

        $request->assertOk();

        $verificationId = $request->json(
            'data.verification_id'
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/otp/verify', [
            'verification_id' => $verificationId,
            'otp' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'OTP verified successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'verification_token',
                    'expires_at',
                ],
            ]);
    }

    public function test_otp_verify_returns_not_found_for_unknown_verification(): void
    {
        $response = $this->postJson('/api/otp/verify', [
            'verification_id' =>
                '00000000-0000-0000-0000-000000000000',
            'otp' => '123456',
        ]);

        $response
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Verification request not found.',
            ]);
    }
}
