<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use Illuminate\Support\Str;
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
    private function __construct(
        public string $value
    ) {
        if ($this->value === '') {
            throw new InvalidArgumentException('TenantId cannot be empty.');
        }
    }

    public static function fromString(string $value): self
    {
        if (! Str::isUuid($value)) {
            throw new InvalidArgumentException("TenantId must be a valid UUID. Received: {$value}");
        }

        return new self($value);
    }

    /**
     * Hydrate a stored event written before UUID tenant migration.
     *
     * Active request/command code must use fromString(). This compatibility
     * boundary normalizes legacy positive integers immediately to strings.
     */
    public static function fromSerialized(int|string $value): self
    {
        $normalized = (string) $value;

        if (Str::isUuid($normalized)) {
            return new self($normalized);
        }

        if (ctype_digit($normalized) && (int) $normalized > 0) {
            return new self($normalized);
        }

        throw new InvalidArgumentException("Serialized TenantId must be a UUID or positive legacy integer. Received: {$normalized}");
    }

    /** @param array{tenant_id?: string, value?: string} $data */
    public static function fromArray(array $data): self
    {
        $value = $data['tenant_id'] ?? $data['value'] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException('TenantId payload must contain tenant_id.');
        }

        return self::fromString($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    /**
     * @return array{tenant_id: string}
     */
    public function jsonSerialize(): array
    {
        return ['tenant_id' => $this->value];
    }
}
