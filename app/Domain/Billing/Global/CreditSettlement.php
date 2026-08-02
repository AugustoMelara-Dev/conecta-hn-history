<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

final readonly class CreditSettlement
{
    public function __construct(
        private string $reservationId,
        private string $status,
        private float $chargedCredits,
        private float $varianceCredits,
        private string $reconciliationStatus,
    ) {}

    public function reservationId(): string
    {
        return $this->reservationId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function chargedCredits(): float
    {
        return $this->chargedCredits;
    }

    public function varianceCredits(): float
    {
        return $this->varianceCredits;
    }

    public function reconciliationStatus(): string
    {
        return $this->reconciliationStatus;
    }
}
