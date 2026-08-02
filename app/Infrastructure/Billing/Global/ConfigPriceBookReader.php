<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Global;

use App\Application\Markets\MarketContext;
use App\Domain\Billing\Global\PlanPrice;
use App\Domain\Billing\Global\PriceBook;
use App\Domain\Billing\Global\PriceBookReaderInterface;

final class ConfigPriceBookReader implements PriceBookReaderInterface
{
    public function forMarket(MarketContext $market): PriceBook
    {
        $marketKey = strtolower(trim($market->countryCode));
        $definition = config("dovra_billing.price_books.{$marketKey}");

        if (! is_array($definition)) {
            throw new \RuntimeException("No price book is configured for market [{$marketKey}].");
        }

        $version = trim((string) ($definition['version'] ?? ''));
        $currency = strtoupper(trim((string) ($definition['currency'] ?? '')));
        $plans = $definition['plans'] ?? null;

        if ($version === '' || $currency === '' || ! is_array($plans)) {
            throw new \RuntimeException("Market [{$marketKey}] price book is invalid.");
        }

        $resolvedPlans = [];
        foreach ($plans as $key => $plan) {
            if (! is_string($key) || ! is_array($plan)) {
                continue;
            }

            $resolvedPlans[strtolower($key)] = new PlanPrice(
                key: strtolower($key),
                currencyCode: $currency,
                monthlyMinor: (int) ($plan['monthly_minor'] ?? 0),
                annualMinor: (int) ($plan['annual_minor'] ?? 0),
                sandboxOnly: (bool) ($plan['sandbox_only'] ?? false),
                liveEntitled: (bool) ($plan['live_entitled'] ?? false),
                includedCredits: (float) ($plan['included_credits'] ?? 0.0),
                capabilities: array_values(array_filter(
                    (array) ($plan['capabilities'] ?? []),
                    static fn (mixed $capability): bool => is_string($capability) && trim($capability) !== '',
                )),
                checkoutMode: (string) ($plan['checkout_mode'] ?? 'external'),
            );
        }

        return new PriceBook(
            version: $version,
            marketKey: $marketKey,
            currencyCode: $currency,
            plans: $resolvedPlans,
        );
    }
}
