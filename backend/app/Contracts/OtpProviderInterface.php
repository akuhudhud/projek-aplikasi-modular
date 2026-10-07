<?php

namespace App\Contracts;

interface OtpProviderInterface
{
    public function send(
        string $channel,
        string $contact,
        string $purpose,
        string $otp
    ): void;
}
