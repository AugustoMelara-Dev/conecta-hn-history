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
        $isUuid = Str::isUuid($this->value);
        $isPositiveInt = ctype_digit($this->value) && (int) $this->value > 0;

        if (! $isUuid && ! $isPositiveInt) {
            throw new InvalidArgumentException("TenantId must be a valid UUID or positive integer. Received: {$this->value}");
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * @param array{tenant_id?: int|string, value?: int|string} $data
     */
    public static function fromArray(array $data): self
    {
        $value = $data['tenant_id'] ?? $data['value'] ?? null;

        if (! is_int($value) && ! is_string($value)) {
            throw new InvalidArgumentException('TenantId payload must contain tenant_id.');
        }

        return new self((string) $value);
    }

    public static function fromInt(int|string $value): self
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value <= 0) {
            throw new InvalidArgumentException("TenantId must be a positive integer. Received: {$value}");
        }

        return new self((string) $value);
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

    public function toInt(): int
    {
        if (! ctype_digit($this->value)) {
            throw new InvalidArgumentException("TenantId is not numeric and cannot be converted to int. Received: {$this->value}");
        }

        return (int) $this->value;
    }
}
