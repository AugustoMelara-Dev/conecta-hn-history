<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * EmailAddress Value Object
 *
 * INVARIANTS:
 * - Must be a valid email format.
 * - Immutable.
 */
final readonly class EmailAddress
{
    public string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format: {$value}");
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
