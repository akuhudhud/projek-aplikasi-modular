<?php

namespace Tests\Feature\Auth;

use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OtpStaleTokenIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_verified_model_cannot_issue_token_after_otp_cycle_is_reset(): void
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

        $service->issueVerificationToken(
            $staleVerification
        );
    }
}
