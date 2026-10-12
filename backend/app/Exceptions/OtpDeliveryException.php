<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class OtpDeliveryException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(
            'OTP delivery failed.',
            0,
            $previous
        );
    }
}
