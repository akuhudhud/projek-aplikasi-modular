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

        return DB::transaction(function () use (
            $account,
            $channel,
            $verificationToken,
            $purpose,
            $contactField
        ) {
            $verification = $this->otpService
                ->consumeVerificationToken(
                    $verificationToken,
                    $purpose,
                    $account->id
                );

            if ($verification->channel !== $channel) {
                throw new RuntimeException(
                    'Verification channel does not match the requested contact change.'
                );
            }

            $exists = Account::query()
                ->where($contactField, $verification->contact)
                ->where('id', '!=', $account->id)
                ->exists();

            if ($exists) {
                throw new RuntimeException(
                    'This contact is already in use.'
                );
            }

            $account->{$contactField} = $verification->contact;

            if ($channel === 'phone') {
                $account->phone_verified_at = now();
            } else {
                $account->email_verified_at = now();
            }

            $account->save();

            return $account->fresh();
        });
    }
}
