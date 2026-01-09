<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * InvalidBusinessRuleException - Thrown when data violates business rules.
 *
 * Examples: quantity < 0, price < 0, items empty, invalid state.
 */
final class InvalidBusinessRuleException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $rule = '',
        private readonly array $violationData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->validateContextSafety($violationData);
    }

    public function context(): array
    {
        return [
            'rule' => $this->rule,
            ...$this->violationData,
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 422; // Unprocessable Entity
    }

    public function getErrorCode(): string
    {
        return 'BUSINESS_RULE_VIOLATION';
    }

    public function rule(): string
    {
        return $this->rule;
    }

    public function violationData(): array
    {
        return $this->violationData;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Factory Methods
    // ═══════════════════════════════════════════════════════════════════════

    public static function invalidQuantity(int $quantity): self
    {
        return new self(
            message: "Cantidad inválida: {$quantity}. Debe ser mayor a 0.",
            rule: 'quantity > 0',
            violationData: ['quantity' => $quantity],
        );
    }

    public static function invalidPrice(int $priceCents): self
    {
        return new self(
            message: "Precio inválido: {$priceCents} centavos. Debe ser mayor o igual a 0.",
            rule: 'price >= 0',
            violationData: ['price_cents' => $priceCents],
        );
    }

    public static function emptyItems(): self
    {
        return new self(
            message: 'La orden debe contener al menos un producto.',
            rule: 'items.count >= 1',
            violationData: [],
        );
    }

    /**
     * @deprecated Anti-Pattern: Do not use generic exceptions for new business rules.
     * Create a specific DomainException class instead to ensure "Screaming Architecture".
     */
    public static function custom(string $message, string $rule = '', array $context = []): self
    {
        return new self($message, $rule, $context);
    }
}
