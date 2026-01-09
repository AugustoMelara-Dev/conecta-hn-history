<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

/**
 * PaymentFailedException - Domain Exception for Payment Gateway Failures
 *
 * Thrown when a payment operation fails at the gateway level.
 */
final class PaymentFailedException extends RuntimeException
{
    public function __construct(
        string $message = 'Payment operation failed',
        public readonly ?string $gatewayCode = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Create from gateway failure response.
     */
    public static function fromGateway(string $reason, ?string $code = null): self
    {
        return new self("Payment failed: {$reason}", $code);
    }
}
