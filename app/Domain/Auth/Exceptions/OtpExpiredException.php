<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use Exception;

/**
 * Thrown when OTP has expired.
 */
final class OtpExpiredException extends Exception
{
    public function __construct(string $message = 'El código OTP ha expirado. Solicita uno nuevo.')
    {
        parent::__construct($message, 422);
    }
}
