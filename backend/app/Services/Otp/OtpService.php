<?php

namespace App\Services\Otp;

use App\Contracts\OtpProviderInterface;
use App\Exceptions\OtpResendException;
use App\Models\RegistrationVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class OtpService
{
    private const OTP = '123456';

    private const OTP_EXPIRY_MINUTES = 5;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_RESENDS = 5;

    public function __construct(
        private readonly OtpProviderInterface $provider
    ) {
    }

    public function request(
        string $channel,
        string $contact,
        string $purpose,
        ?string $accountId = null
    ): RegistrationVerification {
        return DB::transaction(function () use (
            $channel,
            $contact,
            $purpose,
            $accountId
        ) {
            $query = RegistrationVerification::query()
                ->where('channel', $channel)
                ->where('contact', $contact)
                ->where('purpose', $purpose);

            if ($accountId === null) {
                $query->whereNull('account_id');
            } else {
                $query->where('account_id', $accountId);
            }

            $verification = $query
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($verification) {
                if ($verification->last_sent_at !== null) {
                    $cooldownEndsAt = $verification->last_sent_at
                        ->copy()
                        ->addSeconds(self::RESEND_COOLDOWN_SECONDS);

                    if ($cooldownEndsAt->isFuture()) {
                        throw new OtpResendException(
                            'Please wait before requesting another OTP.',
                            max(
                                1,
                                now()->diffInSeconds(
                                    $cooldownEndsAt,
                                    false
                                )
                            )
                        );
                    }
                }

                if ($verification->resend_count >= self::MAX_RESENDS) {
                    throw new OtpResendException(
                        'OTP resend limit reached. Please start a new verification request.',
                        0
                    );
                }

                $this->provider->send(
                    $channel,
                    $contact,
                    $purpose,
                    self::OTP
                );

                $verification->update([
                    'account_id' => $accountId,
                    'otp_hash' => Hash::make(self::OTP),
                    'expires_at' => now()->addMinutes(
                        self::OTP_EXPIRY_MINUTES
                    ),
                    'attempts' => 0,
                    'last_sent_at' => now(),
                    'resend_count' => $verification->resend_count + 1,
                    'verified_at' => null,
                    'verification_token_hash' => null,
                    'token_expires_at' => null,
                    'consumed_at' => null,
                ]);

                return $verification->fresh();
            }

            $this->provider->send(
                $channel,
                $contact,
                $purpose,
                self::OTP
            );

            return RegistrationVerification::create([
                'id' => (string) Str::uuid(),
                'account_id' => $accountId,
                'channel' => $channel,
                'contact' => $contact,
                'purpose' => $purpose,
                'otp_hash' => Hash::make(self::OTP),
                'expires_at' => now()->addMinutes(
                    self::OTP_EXPIRY_MINUTES
                ),
                'attempts' => 0,
                'last_sent_at' => now(),
                'resend_count' => 0,
            ]);
        });
    }

    public function verify(
        RegistrationVerification $verification,
        string $otp
    ): bool {
        return DB::transaction(function () use (
            $verification,
            $otp
        ) {
            $verification->refreshForUpdate();

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
        });
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
            'consumed_at' => null,
        ]);

        return $token;
    }

    public function consumeVerificationToken(
        string $token,
        string $purpose = 'REGISTRATION',
        ?string $accountId = null
    ): RegistrationVerification {
        return DB::transaction(function () use (
            $token,
            $purpose,
            $accountId
        ) {
            $query = RegistrationVerification::query()
                ->where('purpose', $purpose)
                ->where(
                    'verification_token_hash',
                    hash('sha256', $token)
                );

            if ($accountId === null) {
                $query->whereNull('account_id');
            } else {
                $query->where('account_id', $accountId);
            }

            $verification = $query
                ->lockForUpdate()
                ->first();

            if (!$verification) {
                throw new RuntimeException(
                    'Invalid verification token.'
                );
            }

            if ($verification->verified_at === null) {
                throw new RuntimeException(
                    'OTP verification is required before using the verification token.'
                );
            }

            if ($verification->consumed_at !== null) {
                throw new RuntimeException(
                    'Verification token has already been used.'
                );
            }

            if (
                $verification->token_expires_at === null
                || $verification->token_expires_at->isPast()
            ) {
                throw new RuntimeException(
                    'Verification token has expired.'
                );
            }

            $verification->update([
                'consumed_at' => now(),
            ]);

            return $verification->fresh();
        });
    }
}
