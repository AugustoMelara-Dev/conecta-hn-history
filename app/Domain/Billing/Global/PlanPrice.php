<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

final readonly class PlanPrice
{
    /**
     * @param  list<string>  $capabilities
     */
    public function __construct(
        private string $key,
        private string $currencyCode,
        private int $monthlyMinor,
        private int $annualMinor,
        private bool $sandboxOnly,
        private bool $liveEntitled,
        private float $includedCredits,
        private array $capabilities,
        private string $checkoutMode = 'external',
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function monthlyMinor(): int
    {
        return $this->monthlyMinor;
    }

    public function annualMinor(): int
    {
        return $this->annualMinor;
    }

    public function sandboxOnly(): bool
    {
        return $this->sandboxOnly;
    }

    public function liveEntitled(): bool
    {
        return $this->liveEntitled;
    }

    public function includedCredits(): float
    {
        return $this->includedCredits;
    }

    public function checkoutMode(): string
    {
        return $this->checkoutMode;
    }

    public function includesCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
