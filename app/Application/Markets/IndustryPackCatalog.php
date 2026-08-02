<?php

declare(strict_types=1);

namespace App\Application\Markets;

final class IndustryPackCatalog
{
    public function __construct(
        private readonly AvailableMarketCatalog $markets,
    ) {}

    /**
     * Resolve data/configuration for one explicit market. An unknown or
     * incomplete market returns an empty list instead of inheriting HN.
     *
     * @return array<string, mixed>
     */
    public function forMarket(MarketContext $market): array
    {
        $pack = $this->markets->forMarket($market);
        if ($pack === null) {
            return [];
        }

        $capabilities = is_array($pack['capabilities'] ?? null)
            ? $pack['capabilities']
            : [];
        $supportedChannels = $this->enabledKeys($capabilities['channels'] ?? []);
        $addressSchema = is_array($pack['addressSchema'] ?? null)
            ? $pack['addressSchema']
            : [];

        return [
            'packKey' => (string) $pack['packKey'],
            'countryCode' => (string) $pack['countryCode'],
            'locales' => array_values(array_filter(array_column((array) $pack['locales'], 'code'))),
            'currencies' => array_values(array_filter(array_column((array) $pack['currencies'], 'code'))),
            'timezone' => (string) $pack['defaults']['timezone'],
            'supportedChannels' => $supportedChannels,
            'addressSchema' => $addressSchema,
            'taxSchema' => $addressSchema['tax'] ?? null,
            'schemaReferences' => [
                'address' => 'market_pack_versions.address_schema',
                'tax' => array_key_exists('tax', $addressSchema)
                    ? 'market_pack_versions.address_schema.tax'
                    : null,
            ],
            'marketPackVersionId' => (string) $pack['marketPackVersionId'],
            'marketPackVersion' => (int) $pack['marketPackVersion'],
        ];
    }

    /** @return list<string> */
    private function enabledKeys(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_keys($values, true),
            static fn (mixed $key): bool => is_string($key) && trim($key) !== '',
        ));
    }
}
