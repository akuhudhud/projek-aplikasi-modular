<?php

namespace Tests\Feature\Auth;

use App\Contracts\OtpProviderInterface;
use App\Exceptions\OtpDeliveryException;
use App\Models\Account;
use App\Models\RegistrationVerification;
use App\Models\Session;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OtpEndpointTest extends TestCase
{
    use RefreshDatabase;

    private int $phoneCounter = 10000000;

    private function bindFailingOtpProvider(): void
    {
        $this->app->instance(
            OtpProviderInterface::class,
            new class implements OtpProviderInterface {
                public function send(
                    string $channel,
                    string $contact,
                    string $purpose,
                    string $otp
                ): void {
                    throw new RuntimeException(
                        'Simulated OTP delivery failure.'
                    );
                }
            }
        );
    }

    private function createAuthenticatedAccount(
        string $accountId,
        string $token
    ): Account {
        $account = Account::create([
            'id' => $accountId,
            'name' => 'OTP Failure Test',
            'phone' => '601'.$this->phoneCounter++,
            'email' => str_replace('-', '', $accountId)
                .'@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $account;
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    private function assertSafeDeliveryFailure($response): void
    {
        $response
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'OTP tidak dapat dihantar buat masa ini. Sila cuba semula.',
            ])
            ->assertJsonMissing([
                'message' => 'Simulated OTP delivery failure.',
            ]);
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

        $this->assertDatabaseHas('registration_verifications', [
            'account_id' => null,
            'purpose' => 'REGISTRATION',
            'resend_count' => 0,
        ]);
    }

    public function test_otp_request_returns_429_during_resend_cooldown(): void
    {
        $payload = [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ];

        try {
            Carbon::setTestNow('2026-10-07 23:00:00');

            $this->postJson('/api/otp/request', $payload)
                ->assertOk();

            Carbon::setTestNow('2026-10-07 23:00:30');

            $this->postJson('/api/otp/request', $payload)
                ->assertStatus(429)
                ->assertJson([
                    'success' => false,
                    'message' => 'Please wait before requesting another OTP.',
                    'retry_after_seconds' => 30,
                ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_otp_request_returns_429_after_resend_limit(): void
    {
        $payload = [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ];

        try {
            Carbon::setTestNow('2026-10-07 23:00:00');

            $this->postJson('/api/otp/request', $payload)
                ->assertOk();

            for ($resend = 1; $resend <= 5; $resend++) {
                Carbon::setTestNow(
                    '2026-10-07 23:'.str_pad(
                        (string) $resend,
                        2,
                        '0',
                        STR_PAD_LEFT
                    ).':00'
                );

                $this->postJson('/api/otp/request', $payload)
                    ->assertOk();
            }

            Carbon::setTestNow('2026-10-07 23:06:00');

            $this->postJson('/api/otp/request', $payload)
                ->assertStatus(429)
                ->assertJson([
                    'success' => false,
                    'message' => 'OTP resend limit reached. Please start a new verification request.',
                ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_otp_request_rejects_invalid_channel(): void
    {
        $this->postJson('/api/otp/request', [
            'channel' => 'whatsapp',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ])->assertStatus(422);
    }

    public function test_otp_request_rejects_invalid_purpose(): void
    {
        $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'LOGIN',
        ])->assertStatus(422);
    }

    public function test_otp_request_rejects_password_reset_purpose(): void
    {
        $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'PASSWORD_RESET',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['purpose']);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_change_phone_otp_request_requires_authentication(): void
    {
        $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60199999999',
            'purpose' => 'CHANGE_PHONE',
        ])->assertStatus(401)
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

        $this->postJson('/api/otp/verify', [
            'verification_id' => $verificationId,
            'otp' => '123456',
        ])->assertOk()
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

        $this->postJson('/api/otp/verify', [
            'verification_id' => $verificationId,
            'otp' => '000000',
        ])->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ]);
    }

    public function test_otp_verify_returns_not_found_for_unknown_verification(): void
    {
        $this->postJson('/api/otp/verify', [
            'verification_id' =>
                '00000000-0000-0000-0000-000000000000',
            'otp' => '123456',
        ])->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Verification request not found.',
            ]);
    }

    public function test_failed_initial_delivery_does_not_create_verification(): void
    {
        $this->bindFailingOtpProvider();

        try {
            app(OtpService::class)->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );

            $this->fail('Expected OTP delivery to fail.');
        } catch (OtpDeliveryException $exception) {
            $this->assertSame(
                'OTP delivery failed.',
                $exception->getMessage()
            );

            $this->assertSame(
                'Simulated OTP delivery failure.',
                $exception->getPrevious()?->getMessage()
            );
        }

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_failed_resend_preserves_existing_verification(): void
    {
        $originalTime = Carbon::parse('2026-10-12 10:00:00');

        try {
            Carbon::setTestNow($originalTime);

            $verification = app(OtpService::class)->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );

            $originalOtpHash = $verification->otp_hash;
            $originalExpiry = $verification->expires_at
                ->toDateTimeString();
            $originalLastSentAt = $verification->last_sent_at
                ->toDateTimeString();
            $originalResendCount = $verification->resend_count;

            Carbon::setTestNow(
                $originalTime->copy()->addSeconds(61)
            );

            $this->bindFailingOtpProvider();

            try {
                app(OtpService::class)->request(
                    'phone',
                    '60123456789',
                    'REGISTRATION'
                );

                $this->fail('Expected OTP resend to fail.');
            } catch (OtpDeliveryException $exception) {
                $this->assertSame(
                    'OTP delivery failed.',
                    $exception->getMessage()
                );

                $this->assertSame(
                    'Simulated OTP delivery failure.',
                    $exception->getPrevious()?->getMessage()
                );
            }

            $verification->refresh();

            $this->assertSame(
                $originalOtpHash,
                $verification->otp_hash
            );

            $this->assertSame(
                $originalExpiry,
                $verification->expires_at->toDateTimeString()
            );

            $this->assertSame(
                $originalLastSentAt,
                $verification->last_sent_at->toDateTimeString()
            );

            $this->assertSame(
                $originalResendCount,
                $verification->resend_count
            );

            $this->assertSame(
                1,
                RegistrationVerification::query()->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_failed_delivery_for_expired_verification_preserves_existing_record(): void
    {
        $originalTime = Carbon::parse('2026-10-12 10:00:00');

        try {
            Carbon::setTestNow($originalTime);

            $verification = app(OtpService::class)->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );

            $originalOtpHash = $verification->otp_hash;
            $originalLastSentAt = $verification->last_sent_at
                ->toDateTimeString();
            $originalResendCount = $verification->resend_count;

            Carbon::setTestNow(
                $originalTime->copy()->addMinutes(6)
            );

            $this->bindFailingOtpProvider();

            try {
                app(OtpService::class)->request(
                    'phone',
                    '60123456789',
                    'REGISTRATION'
                );

                $this->fail(
                    'Expected expired OTP renewal delivery to fail.'
                );
            } catch (OtpDeliveryException $exception) {
                $this->assertSame(
                    'OTP delivery failed.',
                    $exception->getMessage()
                );
            }

            $verification->refresh();

            $this->assertSame(
                $originalOtpHash,
                $verification->otp_hash
            );

            $this->assertSame(
                $originalLastSentAt,
                $verification->last_sent_at->toDateTimeString()
            );

            $this->assertSame(
                $originalResendCount,
                $verification->resend_count
            );

            $this->assertSame(
                1,
                RegistrationVerification::query()->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_otp_request_endpoint_returns_safe_error_when_delivery_fails(): void
    {
        $this->bindFailingOtpProvider();

        $response = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $this->assertSafeDeliveryFailure($response);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_change_phone_endpoint_returns_safe_error_when_delivery_fails(): void
    {
        $accountId = '11111111-1111-4111-8111-111111111111';
        $token = 'otp-failure-change-phone-token';

        $this->createAuthenticatedAccount($accountId, $token);
        $this->bindFailingOtpProvider();

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->postJson('/api/me/change-phone/request', [
                'phone' => '60198888888',
            ]);

        $this->assertSafeDeliveryFailure($response);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_change_email_endpoint_returns_safe_error_when_delivery_fails(): void
    {
        $accountId = '22222222-2222-4222-8222-222222222222';
        $token = 'otp-failure-change-email-token';

        $this->createAuthenticatedAccount($accountId, $token);
        $this->bindFailingOtpProvider();

        $response = $this
            ->withHeaders($this->authHeaders($token))
            ->postJson('/api/me/change-email/request', [
                'email' => 'delivery-failure@example.com',
            ]);

        $this->assertSafeDeliveryFailure($response);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }
}
