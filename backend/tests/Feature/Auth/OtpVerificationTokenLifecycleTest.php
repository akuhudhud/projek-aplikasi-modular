<?php

namespace Tests\Feature\Auth;

use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OtpVerificationTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

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

        $token = $service->issueVerificationToken(
            $verification
        );

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
        $service = app(OtpService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid verification token.'
        );

        $service->consumeVerificationToken(
            'invalid-token',
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

        $token = $service->issueVerificationToken(
            $verification
        );

        $verification->refresh();

        $verification->update([
            'token_expires_at' => now()->subMinute(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Verification token has expired.'
        );

        $service->consumeVerificationToken(
            $token,
            'REGISTRATION'
        );
    }
}
