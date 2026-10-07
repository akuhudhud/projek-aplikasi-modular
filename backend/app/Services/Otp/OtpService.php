<?php

namespace App\Services\Otp;

use App\Contracts\OtpProviderInterface;
use App\Models\RegistrationVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class OtpService
{
    public function __construct(
        private readonly OtpProviderInterface $provider
    ) {
    }

    public function request(
        string $channel,
        string $contact,
        string $purpose
    ): RegistrationVerification {
        $otp = '123456';

        $verification = RegistrationVerification::query()
            ->where('channel', $channel)
            ->where('contact', $contact)
            ->where('purpose', $purpose)
            ->latest('created_at')
            ->first();

        if ($verification) {
            $verification->update([
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(5),
                'attempts' => 0,
                'verified_at' => null,
                'verification_token_hash' => null,
                'token_expires_at' => null,
            ]);
        } else {
            $verification = RegistrationVerification::create([
                'id' => (string) Str::uuid(),
                'channel' => $channel,
                'contact' => $contact,
                'purpose' => $purpose,
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(5),
                'attempts' => 0,
            ]);
        }

        $this->provider->send(
            $channel,
            $contact,
            $purpose,
            $otp
        );

        return $verification;
    }

    public function verify(
        RegistrationVerification $verification,
        string $otp
    ): bool {
        if ($verification->verified_at !== null) {
            return false;
        }

        if ($verification->expires_at->isPast()) {
            return false;
        }

        if ($verification->attempts >= 5) {
            return false;
        }

        $verification->increment('attempts');

        if (!Hash::check($otp, $verification->otp_hash)) {
            return false;
        }

        $verification->update([
            'verified_at' => now(),
        ]);

        return true;
    }

    public function issueVerificationToken(
        RegistrationVerification $verification
    ): string {
        if ($verification->verified_at === null) {
            throw new RuntimeException(
                'OTP verification is required before issuing a verification token.'
            );
        }

        $token = Str::random(64);

        $verification->update([
            'verification_token_hash' => hash('sha256', $token),
            'token_expires_at' => now()->addMinutes(10),
        ]);

        return $token;
    }
}
