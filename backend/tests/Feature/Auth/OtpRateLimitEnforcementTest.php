<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpRateLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_request_is_limited_by_ip(): void
    {
        $this->useProductionRateLimits();

        try {
            $this->withServerVariables([
                'REMOTE_ADDR' => '192.0.2.41',
            ]);

            for ($attempt = 1; $attempt <= 10; $attempt++) {
                $this->postJson('/api/otp/request', [
                    'channel' => 'phone',
                    'contact' => '6012345'.str_pad(
                        (string) $attempt,
                        4,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'purpose' => 'INVALID_PURPOSE',
                ])->assertStatus(422);
            }

            $this->postJson('/api/otp/request', [
                'channel' => 'phone',
                'contact' => '60123459999',
                'purpose' => 'INVALID_PURPOSE',
            ])->assertStatus(429);
        } finally {
            $this->restoreTestingEnvironment();
        }
    }

    public function test_otp_request_is_limited_by_contact(): void
    {
        $this->useProductionRateLimits();

        try {
            $this->withServerVariables([
                'REMOTE_ADDR' => '192.0.2.42',
            ]);

            $payload = [
                'channel' => 'phone',
                'contact' => '60123456789',
                'purpose' => 'INVALID_PURPOSE',
            ];

            for ($attempt = 1; $attempt <= 6; $attempt++) {
                $this->postJson(
                    '/api/otp/request',
                    $payload
                )->assertStatus(422);
            }

            $this->postJson(
                '/api/otp/request',
                $payload
            )->assertStatus(429);
        } finally {
            $this->restoreTestingEnvironment();
        }
    }

    public function test_otp_verification_is_limited_by_verification_id(): void
    {
        $this->useProductionRateLimits();

        try {
            $this->withServerVariables([
                'REMOTE_ADDR' => '192.0.2.43',
            ]);

            $payload = [
                'verification_id' =>
                    '00000000-0000-0000-0000-000000000041',
                'otp' => '000000',
            ];

            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->postJson(
                    '/api/otp/verify',
                    $payload
                )->assertStatus(404);
            }

            $this->postJson(
                '/api/otp/verify',
                $payload
            )->assertStatus(429);
        } finally {
            $this->restoreTestingEnvironment();
        }
    }

    public function test_otp_verification_is_limited_by_ip(): void
    {
        $this->useProductionRateLimits();

        try {
            $this->withServerVariables([
                'REMOTE_ADDR' => '192.0.2.44',
            ]);

            for ($attempt = 1; $attempt <= 20; $attempt++) {
                $verificationId = sprintf(
                    '00000000-0000-0000-0000-%012d',
                    $attempt
                );

                $this->postJson('/api/otp/verify', [
                    'verification_id' => $verificationId,
                    'otp' => '000000',
                ])->assertStatus(404);
            }

            $this->postJson('/api/otp/verify', [
                'verification_id' =>
                    '00000000-0000-0000-0000-000000000099',
                'otp' => '000000',
            ])->assertStatus(429);
        } finally {
            $this->restoreTestingEnvironment();
        }
    }

    private function useProductionRateLimits(): void
    {
        app()->instance('env', 'production');
    }

    private function restoreTestingEnvironment(): void
    {
        app()->instance('env', 'testing');
    }
}
