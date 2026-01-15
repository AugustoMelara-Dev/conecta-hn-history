<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Shared\ValueObjects\Money;

/**
 * PlanEntity - Immutable Plan Configuration
 *
 * DOMAIN: Billing
 * AXIOM: Financial Precision via Money Value Object.
 */
final readonly class PlanEntity
{
    public function __construct(
        public string $id,
        public string $name,
        public Money $price,
        public string $externalGatewayId
    ) {}

    /**
     * Is this a free plan?
     */
    public function isFree(): bool
    {
        return $this->price->amount === 0;
    }

    /**
     * Convenience: Get price in cents (for legacy compatibility).
     */
    public function priceInCents(): int
    {
        return $this->price->amount;
    }

    /**
     * Convenience: Get currency code.
     */
    public function currency(): string
    {
        return $this->price->currency;
    }
}
