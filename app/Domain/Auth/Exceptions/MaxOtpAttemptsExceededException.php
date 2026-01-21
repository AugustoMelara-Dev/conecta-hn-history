<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

final class MaxOtpAttemptsExceededException extends DomainException
{
    public function __construct(string $message = 'Demasiados intentos. El código ha sido bloqueado.', int $code = 429)
    {
        parent::__construct($message, $code);
    }
}
