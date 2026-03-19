<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * TenantId Value Object
 *
 * Enforces strict validation for Tenant Identifiers (UUID).
 * Updated to support UUID v4/v7 Standards.
 *
 * @property-read string $value
 */
final readonly class TenantId implements JsonSerializable
{
    /** UUID v4 pattern (8-4-4-4-12 hex groups) */
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private function __construct(
        public string $value
    ) {
        if (! preg_match(self::UUID_PATTERN, $this->value)) {
            throw new InvalidArgumentException("TenantId must be a valid UUID string. Received: {$this->value}");
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /** @deprecated Test compatibility layer */
    public static function fromInt(int $id): self
    {
        return new self(sprintf('00000000-0000-0000-4000-%012d', $id));
    }

    /** @deprecated Test compatibility layer */
    public function toInt(): int
    {
        return (int) substr($this->value, -12);
    }

    /**
     * Generate a new random TenantId using Ramsey\Uuid (framework-agnostic).
     */
    public static function generate(): self
    {
        return new self(\Ramsey\Uuid\Uuid::uuid4()->toString());
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
