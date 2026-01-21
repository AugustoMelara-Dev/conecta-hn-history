<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * TenantId Value Object
 *
 * Enforces strict validation for Tenant Identifiers (UUID).
 * Updated to support UUID v4/v7 Standards.
 *
 * @property-read string $value
 */
final readonly class TenantId
{
    private function __construct(
        public string $value
    ) {
        if (!Str::isUuid($this->value)) {
            throw new InvalidArgumentException("TenantId must be a valid UUID string. Received: {$this->value}");
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
