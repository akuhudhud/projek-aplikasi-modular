<?php

namespace Tests\Feature\Auth;

use App\Exceptions\OtpResendException;
use App\Models\RegistrationVerification;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_request_creates_registration_verification(): void
    {
        $verification = app(OtpService::class)->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertInstanceOf(
            RegistrationVerification::class,
            $verification
        );

        $this->assertDatabaseHas('registration_verifications', [
            'id' => $verification->id,
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'attempts' => 0,
            'resend_count' => 0,
            'verified_at' => null,
            'verification_token_hash' => null,
        ]);

        $this->assertNotNull($verification->expires_at);
        $this->assertNotNull($verification->last_sent_at);
        $this->assertTrue($verification->expires_at->isFuture());
    }

    public function test_otp_resend_is_blocked_during_cooldown(): void
    {
        $service = app(OtpService::class);

        try {
            Carbon::setTestNow('2026-10-07 23:00:00');

            $service->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );

            Carbon::setTestNow('2026-10-07 23:00:30');

            try {
                $service->request(
                    'phone',
                    '60123456789',
                    'REGISTRATION'
                );

                $this->fail('Expected resend cooldown exception.');
            } catch (OtpResendException $exception) {
                $this->assertSame(
                    30,
                    $exception->retryAfterSeconds
                );
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_otp_resend_is_allowed_after_cooldown(): void
    {
        $service = app(OtpService::class);

        try {
            Carbon::setTestNow('2026-10-07 23:00:00');

            $service->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );

            Carbon::setTestNow('2026-10-07 23:01:00');

            $verification = $service->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );

            $this->assertSame(1, $verification->resend_count);
            $this->assertSame(0, $verification->attempts);
            $this->assertNull($verification->verified_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_otp_resend_is_blocked_after_maximum_resends(): void
    {
        $service = app(OtpService::class);

        try {
            Carbon::setTestNow('2026-10-07 23:00:00');

            $service->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );

            for ($resend = 1; $resend <= 5; $resend++) {
                Carbon::setTestNow(
                    '2026-10-07 23:'.str_pad(
                        (string) $resend,
                        2,
                        '0',
                        STR_PAD_LEFT
                    ).':00'
                );

                $service->request(
                    'phone',
                    '60123456789',
                    'REGISTRATION'
                );
            }

            Carbon::setTestNow('2026-10-07 23:06:00');

            try {
                $service->request(
                    'phone',
                    '60123456789',
                    'REGISTRATION'
                );

                $this->fail('Expected resend limit exception.');
            } catch (OtpResendException $exception) {
                $this->assertSame(0, $exception->retryAfterSeconds);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_expired_otp_starts_a_new_cycle_after_resend_limit(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $verification->update([
            'expires_at' => now()->subMinute(),
            'last_sent_at' => now()->subMinutes(10),
            'resend_count' => 5,
            'attempts' => 5,
        ]);

        $newVerification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertSame($verification->id, $newVerification->id);
        $this->assertSame(0, $newVerification->resend_count);
        $this->assertSame(0, $newVerification->attempts);
        $this->assertTrue($newVerification->expires_at->isFuture());
        $this->assertNull($newVerification->verified_at);
        $this->assertNull($newVerification->verification_token_hash);
        $this->assertNull($newVerification->token_expires_at);
        $this->assertNull($newVerification->consumed_at);
    }

    public function test_invalid_otp_is_rejected_and_attempt_is_counted(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertFalse(
            $service->verify($verification, '000000')
        );

        $verification->refresh();

        $this->assertNull($verification->verified_at);
        $this->assertSame(1, $verification->attempts);
    }

    public function test_otp_cannot_be_verified_after_expiry(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $verification->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse(
            $service->verify($verification, '123456')
        );
    }

    public function test_otp_verification_is_limited_to_five_attempts(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertFalse(
                $service->verify($verification, '000000')
            );
        }

        $verification->refresh();

        $this->assertSame(5, $verification->attempts);

        $this->assertFalse(
            $service->verify($verification, '123456')
        );

        $verification->refresh();

        $this->assertSame(5, $verification->attempts);
        $this->assertNull($verification->verified_at);
    }

    public function test_verified_otp_cannot_be_reused(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertTrue(
            $service->verify($verification, '123456')
        );

        $verification->refresh();

        $this->assertFalse(
            $service->verify($verification, '123456')
        );
    }

    public function test_verification_reloads_database_state_before_verifying(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $staleVerification = clone $verification;

        RegistrationVerification::query()
            ->whereKey($verification->id)
            ->update([
                'verified_at' => now(),
            ]);

        $this->assertNull($staleVerification->verified_at);

        $this->assertFalse(
            $service->verify($staleVerification, '123456')
        );

        $verification->refresh();

        $this->assertNotNull($verification->verified_at);
        $this->assertSame(0, $verification->attempts);
    }

    public function test_unverified_otp_cannot_issue_verification_token(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'OTP verification is required before issuing a verification token.'
        );

        $service->issueVerificationToken($verification);
    }

    public function test_verified_otp_can_issue_short_lived_verification_token(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertTrue(
            $service->verify($verification, '123456')
        );

        $token = $service->issueVerificationToken($verification);

        $this->assertNotEmpty($token);

        $verification->refresh();

        $this->assertNotNull($verification->verification_token_hash);
        $this->assertNotNull($verification->token_expires_at);
        $this->assertTrue($verification->token_expires_at->isFuture());
        $this->assertSame(
            hash('sha256', $token),
            $verification->verification_token_hash
        );
    }

    public function test_stale_verified_model_cannot_issue_token_after_otp_cycle_reset(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertTrue(
            $service->verify($verification, '123456')
        );

        $staleVerification = clone $verification;

        $verification->update([
            'expires_at' => now()->subMinute(),
        ]);

        $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'OTP verification is required before issuing a verification token.'
        );

        $service->issueVerificationToken($staleVerification);
    }

    public function test_valid_verification_token_can_be_consumed_once(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertTrue(
            $service->verify($verification, '123456')
        );

        $token = $service->issueVerificationToken($verification);

        $consumed = $service->consumeVerificationToken(
            $token,
            'REGISTRATION'
        );

        $this->assertNotNull($consumed->consumed_at);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Verification token has already been used.'
        );

        $service->consumeVerificationToken(
            $token,
            'REGISTRATION'
        );
    }

    public function test_invalid_verification_token_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid verification token.');

        app(OtpService::class)->consumeVerificationToken(
            'invalid-token',
            'REGISTRATION'
        );
    }

    public function test_unverified_verification_token_cannot_be_consumed(): void
    {
        $verification = RegistrationVerification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'otp_hash' => \Illuminate\Support\Facades\Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verification_token_hash' => hash('sha256', 'unverified-token'),
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $this->assertNull($verification->verified_at);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'OTP verification is required before using the verification token.'
        );

        app(OtpService::class)->consumeVerificationToken(
            'unverified-token',
            'REGISTRATION'
        );
    }

    public function test_expired_verification_token_is_rejected(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertTrue(
            $service->verify($verification, '123456')
        );

        $token = $service->issueVerificationToken($verification);

        $verification->update([
            'token_expires_at' => now()->subMinute(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Verification token has expired.');

        $service->consumeVerificationToken(
            $token,
            'REGISTRATION'
        );
    }

    public function test_verification_token_cannot_be_used_for_a_different_purpose(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertTrue(
            $service->verify($verification, '123456')
        );

        $token = $service->issueVerificationToken($verification);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid verification token.');

        $service->consumeVerificationToken(
            $token,
            'PASSWORD_RESET'
        );
    }

    public function test_verification_token_cannot_be_used_by_a_different_account(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertTrue(
            $service->verify($verification, '123456')
        );

        $token = $service->issueVerificationToken($verification);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid verification token.');

        $service->consumeVerificationToken(
            $token,
            'REGISTRATION',
            'different-account-id'
        );
    }
}
