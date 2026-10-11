<?php

namespace Tests\Feature\Auth;

use App\Models\RegistrationVerification;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpExpiredCycleResetTest extends TestCase
{
    use RefreshDatabase;

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

        $this->assertSame(
            $verification->id,
            $newVerification->id
        );

        $this->assertSame(
            0,
            $newVerification->resend_count
        );

        $this->assertSame(
            0,
            $newVerification->attempts
        );

        $this->assertTrue(
            $newVerification->expires_at->isFuture()
        );

        $this->assertNull(
            $newVerification->verified_at
        );

        $this->assertNull(
            $newVerification->verification_token_hash
        );

        $this->assertNull(
            $newVerification->token_expires_at
        );

        $this->assertNull(
            $newVerification->consumed_at
        );
    }
}
