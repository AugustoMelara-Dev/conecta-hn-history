<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * PaymentGatewayUnavailableException - Domain Exception for Gateway Failures
 *
 * Thrown when the payment gateway is unreachable or experiencing issues.
 */
final class PaymentGatewayUnavailableException extends DomainException
{
    public function __construct(
        string $message = 'Payment gateway is temporarily unavailable',
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
        return 'PAYMENT_GATEWAY_UNAVAILABLE';
    }
}
