<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * TenantId Value Object
 *
 * Enforces strict validation for Tenant Identifiers.
 * Impossible to instantiate in an invalid state.
 *
 * @property-read int $value
 */
final readonly class TenantId
{
    public function __construct(
        public int $value
    ) {
        if ($this->value <= 0) {
            throw new InvalidArgumentException("TenantId must be a positive integer. Received: {$this->value}");
        }
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
