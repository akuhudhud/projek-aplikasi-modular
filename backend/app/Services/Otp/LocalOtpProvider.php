<?php

namespace App\Services\Otp;

use App\Contracts\OtpProviderInterface;

class LocalOtpProvider implements OtpProviderInterface
{
    public function send(
        string $channel,
        string $contact,
        string $purpose,
        string $otp
    ): void {
        // Development provider.
        // The OTP is intentionally not sent through an external
        // SMS/email service during local development.
    }
}
