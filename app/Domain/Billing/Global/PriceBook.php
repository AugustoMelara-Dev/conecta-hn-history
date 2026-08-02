<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

final readonly class PriceBook
{
    /**
     * @param  array<string, PlanPrice>  $plans
     */
    public function __construct(
        private string $version,
        private string $marketKey,
        private string $currencyCode,
        private array $plans,
    ) {}

    public function version(): string
    {
        return $this->version;
    }

    public function marketKey(): string
    {
        return $this->marketKey;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function plan(string $key): PlanPrice
    {
        $normalized = strtolower(trim($key));

        if (! isset($this->plans[$normalized])) {
            throw new \RuntimeException("Unknown plan [{$normalized}] in price book.");
        }

        return $this->plans[$normalized];
    }
}
