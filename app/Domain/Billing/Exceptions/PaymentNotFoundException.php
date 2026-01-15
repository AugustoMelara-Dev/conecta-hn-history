<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use DomainException;

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
    public static function withId(string $id): self
    {
        return new self("Payment with ID [{$id}] not found.");
    }
}
