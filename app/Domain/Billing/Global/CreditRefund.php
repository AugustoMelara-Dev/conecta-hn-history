<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

final readonly class CreditRefund
{
    public function __construct(
        private string $reservationId,
        private string $status,
    ) {}

    public function reservationId(): string
    {
        return $this->reservationId;
    }

    public function status(): string
    {
        return $this->status;
    }
}
