<?php

declare(strict_types=1);

namespace App\Application\Markets;

final class TenantMarketResolver
{
    public function resolveOrNull(object $tenant): ?MarketContext
    {
        try {
            return $this->resolve($tenant);
        } catch (MissingMarketContextException) {
            return null;
        }
    }

    public function resolve(object $tenant): MarketContext
    {
        if (! method_exists($tenant, 'primaryMarket') || ! method_exists($tenant, 'getKey')) {
            throw new \InvalidArgumentException('Market context can only be resolved for a tenant aggregate.');
        }

        $market = $tenant->relationLoaded('primaryMarket')
            ? $tenant->primaryMarket
            : $tenant->primaryMarket()->first();

        if ($market !== null) {
            return new MarketContext(
                countryCode: (string) $market->country_code,
                localeCode: (string) $market->locale_code,
                currencyCode: (string) $market->currency_code,
                timezone: (string) $market->timezone_name,
                source: 'tenant_market',
            );
        }

        if ($tenant->city_id !== null) {
            return new MarketContext(
                countryCode: 'HN',
                localeCode: 'es-HN',
                currencyCode: 'HNL',
                timezone: 'America/Tegucigalpa',
                source: 'legacy_honduras_city',
            );
        }

        throw new MissingMarketContextException(
            "Tenant [{$tenant->getKey()}] has no explicit market context.",
        );
    }

    /**
     * Resolve only the canonical tenant market. Legacy city shadow reads are
     * intentionally excluded from new global Discover/Markets workflows.
     */
    public function resolveExplicit(object $tenant): MarketContext
    {
        if (! method_exists($tenant, 'primaryMarket') || ! method_exists($tenant, 'getKey')) {
            throw new \InvalidArgumentException('Market context can only be resolved for a tenant aggregate.');
        }

        $market = $tenant->relationLoaded('primaryMarket')
            ? $tenant->primaryMarket
            : $tenant->primaryMarket()->first();

        if ($market === null) {
            throw new MissingMarketContextException(
                "Tenant [{$tenant->getKey()}] has no canonical market context.",
            );
        }

        return new MarketContext(
            countryCode: (string) $market->country_code,
            localeCode: (string) $market->locale_code,
            currencyCode: (string) $market->currency_code,
            timezone: (string) $market->timezone_name,
            source: 'tenant_market',
        );
    }
}
