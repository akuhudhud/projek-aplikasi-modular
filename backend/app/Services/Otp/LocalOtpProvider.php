<?php

namespace App\Services\Otp;

use App\Contracts\OtpProviderInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LocalOtpProvider implements OtpProviderInterface
{
    public function send(
        string $channel,
        string $contact,
        string $purpose,
        string $otp
    ): void {
        if (!app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'Local OTP provider cannot be used outside local or testing environments.'
            );
        }

        if (app()->environment('testing')) {
            return;
        }

        Log::channel('single')->debug('Local development OTP', [
            'channel' => $channel,
            'purpose' => $purpose,
            'otp' => $otp,
        ]);
    }
}
