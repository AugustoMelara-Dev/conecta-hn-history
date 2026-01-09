<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * OrderNumber - Immutable Value Object for Order Identification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DOMAIN IDENTITY: Type-Safe Order Reference                             ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  ORDER NUMBER vs ORDER ID:                                              ║
 * ║    - Order ID: Internal database primary key (auto-increment)           ║
 * ║    - Order Number: Human-readable, tenant-scoped reference              ║
 * ║                                                                          ║
 * ║  FORMAT EXAMPLES:                                                       ║
 * ║    - "ORD-00001" (default sequential)                                   ║
 * ║    - "TENANT-2024-00123" (tenant-prefixed yearly)                       ║
 * ║    - "A1B2C3D4" (random alphanumeric)                                  ║
 * ║                                                                          ║
 * ║  VALIDATION:                                                            ║
 * ║    - Cannot be empty or whitespace-only                                 ║
 * ║    - Trimmed on construction to prevent leading/trailing spaces         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   $orderNumber = new OrderNumber($order->order_number);
 *   $event = new OrderCancelled($orderNumber, ...);
 */
final readonly class OrderNumber implements \JsonSerializable
{
    /**
     * @param  string  $value  The order number string (non-empty, trimmed)
     *
     * @throws InvalidArgumentException If value is empty after trimming
     */
    public function __construct(
        public string $value
    ) {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException(
                'OrderNumber cannot be empty or whitespace-only'
            );
        }

        // Re-assign the trimmed value
        // Note: In PHP 8.1+ readonly, we must validate before assignment
        // Since constructor promotion assigns first, we validate after
        if ($this->value !== $trimmed) {
            throw new InvalidArgumentException(
                'OrderNumber must not have leading or trailing whitespace'
            );
        }
    }

    /**
     * Create from a raw string (factory method with auto-trim).
     */
    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    /**
     * Strict equality comparison.
     */
    public function equals(OrderNumber $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Get the string value for display or storage.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // JsonSerializable Implementation (Queue-Safe)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Serialize to JSON-safe array.
     */
    public function jsonSerialize(): array
    {
        return ['number' => $this->value];
    }

    /**
     * Reconstruct from serialized array.
     */
    public static function fromArray(array $data): self
    {
        return new self($data['number']);
    }
}
