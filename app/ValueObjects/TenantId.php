<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * TenantId - Immutable Value Object for Tenant Identification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MULTI-TENANCY SAFETY: Type-Safe Tenant Context                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  WHY NOT USE A RAW INT?                                                 ║
 * ║    - int $tenantId can accidentally receive order_id, user_id, etc.     ║
 * ║    - No compile-time guarantee that the int is a valid tenant           ║
 * ║    - Cross-tenant bugs are silent and catastrophic                      ║
 * ║                                                                          ║
 * ║  WHY USE TenantId VALUE OBJECT?                                         ║
 * ║    - Type system enforces correct usage: function(TenantId $id)         ║
 * ║    - Constructor validates the value (must be > 0)                      ║
 * ║    - Impossible to accidentally mix tenant IDs with other integers      ║
 * ║    - Serialization-friendly for queue payloads                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   $tenantId = new TenantId($order->tenant_id);
 *   $event = new OrderPaid($tenantId, ...);
 */
final readonly class TenantId implements \JsonSerializable
{
    /**
     * @param int $value The tenant identifier (must be > 0)
     * @throws InvalidArgumentException If value is not a positive integer
     */
    public function __construct(
        public int $value
    ) {
        if ($value <= 0) {
            throw new InvalidArgumentException(
                "TenantId must be a positive integer, received: {$value}"
            );
        }
    }

    /**
     * Create from an integer (factory method for explicit construction).
     */
    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    /**
     * Strict equality comparison.
     *
     * VALUE OBJECT PATTERN: Two TenantIds are equal if their values are equal.
     * Identity is based on value, not reference.
     */
    public function equals(TenantId $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Check if this tenant ID matches a raw integer.
     *
     * Useful for database queries: ->where('tenant_id', $tenantId->toInt())
     */
    public function toInt(): int
    {
        return $this->value;
    }

    /**
     * String representation for debugging and logging.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // JsonSerializable Implementation (Queue-Safe)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Serialize to JSON-safe array.
     */
    public function jsonSerialize(): array
    {
        return ['tenant_id' => $this->value];
    }

    /**
     * Reconstruct from serialized array.
     */
    public static function fromArray(array $data): self
    {
        return new self($data['tenant_id']);
    }
}
