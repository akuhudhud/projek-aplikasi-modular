<?php

namespace Tests\Feature\Auth;

use App\Exceptions\OtpResendException;
use App\Models\RegistrationVerification;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_request_creates_registration_verification(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
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

        $this->assertTrue(
            $verification->expires_at->isFuture()
        );
    }

    public function test_otp_resend_is_blocked_during_cooldown(): void
    {
        $service = app(OtpService::class);

        Carbon::setTestNow('2026-10-07 23:00:00');

        $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        Carbon::setTestNow('2026-10-07 23:00:30');

        $this->expectException(OtpResendException::class);

        try {
            $service->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );
        } catch (OtpResendException $exception) {
            $this->assertSame(
                30,
                $exception->retryAfterSeconds
            );

            throw $exception;
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_otp_resend_is_allowed_after_cooldown_and_increments_count(): void
    {
        $service = app(OtpService::class);

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

        $this->assertSame(
            1,
            $verification->resend_count
        );

        $this->assertSame(
            0,
            $verification->attempts
        );

        $this->assertNull(
            $verification->verified_at
        );

        Carbon::setTestNow();
    }

    public function test_otp_resend_is_blocked_after_maximum_resends(): void
    {
        $service = app(OtpService::class);

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

        $this->expectException(OtpResendException::class);

        try {
            $service->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_invalid_otp_is_rejected(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->assertFalse(
            $service->verify(
                $verification,
                '000000'
            )
        );

        $verification->refresh();

        $this->assertNull(
            $verification->verified_at
        );

        $this->assertSame(
            1,
            $verification->attempts
        );
    }

    public function test_verification_token_requires_verified_otp(): void
    {
        $service = app(OtpService::class);

        $verification = $service->request(
            'phone',
            '60123456789',
            'REGISTRATION'
        );

        $this->expectException(
            \RuntimeException::class
        );

        $service->issueVerificationToken(
            $verification
        );
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
            $service->verify(
                $verification,
                '123456'
            )
        );

        $token = $service->issueVerificationToken(
            $verification
        );

        $this->assertNotEmpty($token);

        $verification->refresh();

        $this->assertNotNull(
            $verification->verification_token_hash
        );

        $this->assertNotNull(
            $verification->token_expires_at
        );

        $this->assertTrue(
            $verification->token_expires_at->isFuture()
        );

        $this->assertSame(
            hash('sha256', $token),
            $verification->verification_token_hash
        );
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
            $service->verify(
                $verification,
                '123456'
            )
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
                $service->verify(
                    $verification,
                    '000000'
                )
            );
        }

        $verification->refresh();

        $this->assertSame(
            5,
            $verification->attempts
        );

        $this->assertFalse(
            $service->verify(
                $verification,
                '123456'
            )
        );

        $verification->refresh();

        $this->assertSame(
            5,
            $verification->attempts
        );

        $this->assertNull(
            $verification->verified_at
        );
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
            $service->verify(
                $verification,
                '123456'
            )
        );

        $verification->refresh();

        $this->assertFalse(
            $service->verify(
                $verification,
                '123456'
            )
        );
    }
}
