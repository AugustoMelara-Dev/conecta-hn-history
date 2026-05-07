<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * UnknownPaymentException - Domain Exception for Unexpected Payment Errors
 *
 * Thrown when an unexpected error occurs during payment processing.
 * The original exception is captured for logging purposes.
 */
final class UnknownPaymentException extends DomainException
{
    public function __construct(
        string $message = 'An unexpected payment error occurred',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function context(): array
    {
        return [];
    }

    public function getErrorCode(): string
    {
        return 'UNKNOWN_PAYMENT_ERROR';
    }
}
