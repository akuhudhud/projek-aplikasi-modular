<?php

namespace App\Exceptions;

use RuntimeException;

class OtpResendException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfterSeconds = 0
    ) {
        parent::__construct($message);
    }
}
