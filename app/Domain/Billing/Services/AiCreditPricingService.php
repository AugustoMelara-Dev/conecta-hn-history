<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

final class AiCreditPricingService
{
    public function fromProviderUsd(float $providerCostUsd): float
    {
        if (! is_finite($providerCostUsd) || $providerCostUsd < 0) {
            throw new \InvalidArgumentException('Provider cost must be a finite non-negative amount.');
        }

        if ($providerCostUsd === 0.0) {
            return 0.0;
        }

        return $this->fromProviderUsdAtSnapshot(
            $providerCostUsd,
            $this->creditsPerProviderUsd(),
            $this->minimumCharge(),
        );
    }

    public function fromProviderUsdAtSnapshot(float $providerCostUsd, float $creditsPerProviderUsd, float $minimumCharge): float
    {
        if (! is_finite($providerCostUsd) || $providerCostUsd < 0) {
            throw new \InvalidArgumentException('Provider cost must be a finite non-negative amount.');
        }

        if (! is_finite($creditsPerProviderUsd) || $creditsPerProviderUsd <= 0 || ! is_finite($minimumCharge) || $minimumCharge < 0) {
            throw new \InvalidArgumentException('Commercial AI credit pricing snapshot is invalid.');
        }

        if ($providerCostUsd === 0.0) {
            return 0.0;
        }

        return ceil(max($minimumCharge, $providerCostUsd * $creditsPerProviderUsd) * 1_000_000) / 1_000_000;
    }

    public function version(): string
    {
        $version = trim((string) config('ai.commercial_credits.pricing_version', 'credits-v1'));

        if ($version === '' || mb_strlen($version) > 32) {
            throw new \LogicException('Commercial AI credit pricing version is invalid.');
        }

        return $version;
    }

    public function creditsPerProviderUsd(): float
    {
        $rate = (float) config('ai.commercial_credits.credits_per_provider_usd', 20.0);

        if (! is_finite($rate) || $rate <= 0) {
            throw new \LogicException('Commercial AI credit conversion rate is invalid.');
        }

        return $rate;
    }

    public function minimumCharge(): float
    {
        $minimum = (float) config('ai.commercial_credits.minimum_charge', 0.01);

        if (! is_finite($minimum) || $minimum < 0) {
            throw new \LogicException('Commercial AI credit minimum charge is invalid.');
        }

        return $minimum;
    }
}
