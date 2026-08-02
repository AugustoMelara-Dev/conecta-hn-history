<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

final readonly class CreditReservation
{
    public function __construct(
        private string $reservationId,
        private string $tenantId,
        private float $authorizedCredits,
        private string $status,
        private string $idempotencyKey,
    ) {}

    public function reservationId(): string
    {
        return $this->reservationId;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function authorizedCredits(): float
    {
        return $this->authorizedCredits;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}
