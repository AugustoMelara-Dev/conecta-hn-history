<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use DomainException;

/**
 * TooManyOtpRequestsException - Rate Limiting Exception for OTP Generation
 */
final class TooManyOtpRequestsException extends DomainException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        string $message = 'Demasiadas solicitudes de código. Intenta de nuevo más tarde.'
    ) {
        parent::__construct($message);
    }

    public static function forUser(string $identifier, int $retryAfter): self
    {
        return new self(
            retryAfterSeconds: $retryAfter,
            message: "OTP generation rate limit exceeded for {$identifier}"
        );
    }
}
