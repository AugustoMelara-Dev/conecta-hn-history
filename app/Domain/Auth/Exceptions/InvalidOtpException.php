<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use Exception;

/**
 * Thrown when OTP verification fails due to invalid code.
 */
final class InvalidOtpException extends Exception
{
    public function __construct(string $message = 'Código OTP inválido o no encontrado.')
    {
        parent::__construct($message, 422);
    }
}
