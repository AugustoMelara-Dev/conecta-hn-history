<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * InvalidBusinessRuleException - Thrown when data violates business rules.
 *
 * Examples: quantity < 0, price < 0, items empty.
 */
class InvalidBusinessRuleException extends DataIntegrityException
{
    public static function invalidQuantity(int $quantity): self
    {
        return new self(
            "Invalid quantity: {$quantity}. Must be greater than 0.",
            ['quantity' => $quantity, 'rule' => 'quantity > 0']
        );
    }

    public static function invalidPrice(int $priceCents): self
    {
        return new self(
            "Invalid price: {$priceCents} cents. Must be greater than or equal to 0.",
            ['price_cents' => $priceCents, 'rule' => 'price >= 0']
        );
    }

    public static function emptyItems(): self
    {
        return new self(
            "Order must contain at least one item.",
            ['rule' => 'items.count >= 1']
        );
    }

    public static function custom(string $message, array $context = []): self
    {
        return new self($message, $context);
    }
}
