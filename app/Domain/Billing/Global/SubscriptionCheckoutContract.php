<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

final readonly class SubscriptionCheckoutContract
{
    public function __construct(
        private string $mode,
        private string $providerKey,
    ) {}

    public function mode(): string
    {
        return $this->mode;
    }

    public function providerKey(): string
    {
        return $this->providerKey;
    }
}
