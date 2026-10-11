<?php

namespace Tests\Feature\Auth;

use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OtpVerificationTokenPurposeTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_token_cannot_be_used_for_a_different_purpose(): void
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

        $this->expectException(RuntimeException::class);

        $this->expectExceptionMessage(
            'Invalid verification token.'
        );

        $service->consumeVerificationToken(
            $token,
            'PASSWORD_RESET'
        );
    }
}
