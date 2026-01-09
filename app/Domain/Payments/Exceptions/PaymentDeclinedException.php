<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

/**
 * PaymentDeclinedException - Domain Exception for Declined Payments
 *
 * Thrown when a payment is declined by the card issuer or payment processor.
 */
final class PaymentDeclinedException extends \DomainException
{
    public function __construct(
        public readonly string $declineCode,
        string $message = 'Payment was declined',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
