<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

final readonly class EntitlementDecision
{
    public function __construct(
        private bool $allowed,
        private string $reason,
        private string $status,
    ) {}

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): string
    {
        return $this->status;
    }
}
