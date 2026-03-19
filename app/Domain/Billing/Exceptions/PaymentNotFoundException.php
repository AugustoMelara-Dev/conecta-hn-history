<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * PaymentNotFoundException - Domain Exception
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SEMANTIC ERROR: Speaks business language ("Payment missing"), not SQL.  ║
 * ║  FRAMEWORK AGNOSTIC: Extends DomainException (PHP Standard), not Laravel.║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PaymentNotFoundException extends DomainException
{
    private function __construct(
        string $message,
        private readonly ?string $paymentId = null
    ) {
        parent::__construct($message);
    }

    public static function withId(string $id): self
    {
        return new self("Payment with ID [{$id}] not found.", $id);
    }

    /**
     * @return array{payment_id: string|null}
     */
    public function context(): array
    {
        return ['payment_id' => $this->paymentId];
    }

    public function getErrorCode(): string
    {
        return 'PAYMENT_NOT_FOUND';
    }
}
