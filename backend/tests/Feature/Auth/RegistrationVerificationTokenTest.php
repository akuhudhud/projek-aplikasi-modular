<?php

namespace Tests\Feature\Auth;

use App\Models\RegistrationVerification;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationVerificationTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_registration_token_can_be_consumed(): void
    {
        $verification = RegistrationVerification::create([
            'id' => (string) Str::uuid(),
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', 'registration-token'),
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $service = app(OtpService::class);

        $result = $service->consumeVerificationToken(
            'registration-token'
        );

        $this->assertSame(
            $verification->id,
            $result->id
        );

        $this->assertNotNull($result->consumed_at);
    }

    public function test_unverified_registration_token_cannot_be_consumed(): void
    {
        RegistrationVerification::create([
            'id' => (string) Str::uuid(),
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verification_token_hash' => hash('sha256', 'registration-token'),
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $this->expectException(\RuntimeException::class);

        app(OtpService::class)->consumeVerificationToken(
            'registration-token'
        );
    }

    public function test_expired_registration_token_cannot_be_consumed(): void
    {
        RegistrationVerification::create([
            'id' => (string) Str::uuid(),
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', 'registration-token'),
            'token_expires_at' => now()->subMinute(),
        ]);

        $this->expectException(\RuntimeException::class);

        app(OtpService::class)->consumeVerificationToken(
            'registration-token'
        );
    }

    public function test_consumed_registration_token_cannot_be_reused(): void
    {
        RegistrationVerification::create([
            'id' => (string) Str::uuid(),
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', 'registration-token'),
            'token_expires_at' => now()->addMinutes(10),
            'consumed_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);

        app(OtpService::class)->consumeVerificationToken(
            'registration-token'
        );
    }

    public function test_wrong_purpose_token_cannot_be_consumed_as_registration(): void
    {
        RegistrationVerification::create([
            'id' => (string) Str::uuid(),
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'PASSWORD_RESET',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', 'registration-token'),
            'token_expires_at' => now()->addMinutes(10),
        ]);

        $this->expectException(\RuntimeException::class);

        app(OtpService::class)->consumeVerificationToken(
            'registration-token',
            'REGISTRATION'
        );
    }
}
