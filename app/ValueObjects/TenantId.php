<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Legacy event TenantId value object.
 *
 * Event payloads still serialize integer tenant ids while the newer domain
 * tenant id object is UUID-only. Keep this compatibility layer until the
 * event contracts are upgraded in one pass.
 */
final readonly class TenantId
{
    public function __construct(
        private int|string $value,
    ) {
        if ((string) $this->value === '') {
            throw new InvalidArgumentException('TenantId cannot be empty');
        }
    }

    public static function fromInt(int|string $value): self
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('TenantId must be numeric for legacy event ids');
        }

        return new self((int) $value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toInt(): int
    {
        return (int) $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
