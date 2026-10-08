<?php

namespace App\Services\Contact;

use App\Models\Account;
use App\Models\RegistrationVerification;
use App\Services\Otp\OtpService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContactChangeService
{
    public function __construct(
        private readonly OtpService $otpService
    ) {
    }

    public function request(
        Account $account,
        string $channel,
        string $contact
    ): RegistrationVerification {
        if ($account->status !== 'ACTIVE') {
            throw new RuntimeException(
                'Only active accounts can change contact information.'
            );
        }

        $purpose = $channel === 'phone'
            ? 'CHANGE_PHONE'
            : 'CHANGE_EMAIL';

        $contactField = $channel === 'phone'
            ? 'phone'
            : 'email';

        if ($account->{$contactField} === $contact) {
            throw new RuntimeException(
                'The new contact must be different from the current contact.'
            );
        }

        $exists = Account::query()
            ->where($contactField, $contact)
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                'This contact is already in use.'
            );
        }

        return $this->otpService->request(
            $channel,
            $contact,
            $purpose,
            $account->id
        );
    }

    public function complete(
        Account $account,
        string $channel,
        string $verificationToken
    ): Account {
        $purpose = $channel === 'phone'
            ? 'CHANGE_PHONE'
            : 'CHANGE_EMAIL';

        $contactField = $channel === 'phone'
            ? 'phone'
            : 'email';

        return DB::transaction(function () use (
            $account,
            $channel,
            $verificationToken,
            $purpose,
            $contactField
        ) {
            $lockedAccount = Account::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedAccount) {
                throw new RuntimeException(
                    'Account not found.'
                );
            }

            if ($lockedAccount->status !== 'ACTIVE') {
                throw new RuntimeException(
                    'Only active accounts can change contact information.'
                );
            }

            $verification = $this->otpService
                ->consumeVerificationToken(
                    $verificationToken,
                    $purpose,
                    $lockedAccount->id
                );

            if ($verification->channel !== $channel) {
                throw new RuntimeException(
                    'Verification channel does not match the requested contact change.'
                );
            }

            $exists = Account::query()
                ->where($contactField, $verification->contact)
                ->where('id', '!=', $lockedAccount->id)
                ->exists();

            if ($exists) {
                throw new RuntimeException(
                    'This contact is already in use.'
                );
            }

            $lockedAccount->{$contactField} = $verification->contact;

            if ($channel === 'phone') {
                $lockedAccount->phone_verified_at = now();
            } else {
                $lockedAccount->email_verified_at = now();
            }

            $lockedAccount->save();

            return $lockedAccount->fresh();
        });
    }
}
