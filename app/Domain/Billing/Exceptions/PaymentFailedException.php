<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * PaymentFailedException - Domain Exception for Payment Gateway Failures
 *
 * Thrown when a payment operation fails at the gateway level.
 */
final class PaymentFailedException extends DomainException
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

    /**
     * @return array{gateway_code: string|null}
     */
    public function context(): array
    {
        return ['gateway_code' => $this->gatewayCode];
    }

    public function getErrorCode(): string
    {
        return 'PAYMENT_FAILED';
    }
}
