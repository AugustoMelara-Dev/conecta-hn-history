<?php

declare(strict_types=1);

namespace App\Application\Markets;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AvailableMarketCatalog
{
    /**
     * Return only registration-ready, explicitly published market packs.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->allInternal(false);
    }

    /**
     * Return the same registration-ready packs with their stable pack key and
     * version capabilities. The legacy `all()` response stays unchanged for
     * existing registration consumers.
     *
     * @return list<array<string, mixed>>
     */
    public function allWithPackMetadata(): array
    {
        return $this->allInternal(true);
    }

    /**
     * Resolve an explicitly supplied market without falling back to HN.
     *
     * @return array<string, mixed>|null
     */
    public function forMarket(MarketContext $market): ?array
    {
        $countryCode = strtoupper(trim($market->countryCode));
        $localeCode = trim($market->localeCode);
        $currencyCode = strtoupper(trim($market->currencyCode));
        $timezone = trim($market->timezone);

        foreach ($this->allWithPackMetadata() as $pack) {
            $defaults = (array) ($pack['defaults'] ?? []);
            $locales = array_column((array) ($pack['locales'] ?? []), 'code');
            $currencies = array_column((array) ($pack['currencies'] ?? []), 'code');
            $timezones = array_column((array) ($pack['timezones'] ?? []), 'name');

            if ((string) ($pack['countryCode'] ?? '') !== $countryCode
                || ! in_array($localeCode, $locales, true)
                || ! in_array($currencyCode, $currencies, true)
                || ! in_array($timezone, $timezones, true)
                || ($defaults['localeCode'] ?? null) === null) {
                continue;
            }

            return $pack;
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allInternal(bool $includePackMetadata): array
    {
        return DB::table('market_packs as packs')
            ->join('countries', 'countries.code', '=', 'packs.country_code')
            ->where('packs.is_active', true)
            ->where('countries.is_active', true)
            ->orderBy('packs.country_code')
            ->get([
                'packs.id',
                'packs.key as pack_key',
                'packs.country_code',
                'countries.name as country_name',
                'countries.calling_code',
            ])
            ->map(fn (object $pack): ?array => $this->serialize($pack, $includePackMetadata))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serialize(object $pack, bool $includePackMetadata = false): ?array
    {
        $version = DB::table('market_pack_versions')
            ->where('market_pack_id', $pack->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', now());
            })
            ->orderByDesc('version')
            ->first();

        if ($version === null) {
            return null;
        }

        $localeCodes = $this->strings($this->decodeJson($version->supported_locales));
        $currencyCodes = $this->strings($this->decodeJson($version->supported_currencies));
        $timezoneNames = $this->strings($this->decodeJson($version->supported_timezones));

        $locales = DB::table('locales')
            ->where('is_active', true)
            ->whereIn('code', $localeCodes)
            ->get(['code', 'name', 'native_name'])
            ->keyBy('code');
        $currencies = DB::table('currencies')
            ->where('is_active', true)
            ->whereIn('code', $currencyCodes)
            ->get(['code', 'name', 'symbol'])
            ->keyBy('code');
        $timezones = DB::table('timezones')
            ->where('is_active', true)
            ->whereIn('name', $timezoneNames)
            ->get(['name', 'label'])
            ->keyBy('name');
        $localities = DB::table('localities')
            ->leftJoin('administrative_areas', 'administrative_areas.id', '=', 'localities.administrative_area_id')
            ->where('localities.country_code', $pack->country_code)
            ->where('localities.is_active', true)
            ->orderBy('localities.name')
            ->get([
                'localities.id',
                'localities.name',
                'localities.timezone_name',
                'administrative_areas.name as administrative_area',
            ]);

        if (
            ! $locales->has($version->default_locale_code)
            || ! $currencies->has($version->default_currency_code)
            || ! $timezones->has($version->default_timezone_name)
            || $localities->isEmpty()
        ) {
            return null;
        }

        $result = [
            'countryCode' => $pack->country_code,
            'countryName' => $pack->country_name,
            'callingCode' => $pack->calling_code,
            'marketPackVersionId' => (string) $version->id,
            'marketPackVersion' => (int) $version->version,
            'defaults' => [
                'localeCode' => $version->default_locale_code,
                'currencyCode' => $version->default_currency_code,
                'timezone' => $version->default_timezone_name,
            ],
            'locales' => $this->ordered($localeCodes, $locales, fn (object $locale): array => [
                'code' => $locale->code,
                'name' => $locale->name,
                'nativeName' => $locale->native_name,
            ]),
            'currencies' => $this->ordered($currencyCodes, $currencies, fn (object $currency): array => [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
            ]),
            'timezones' => $this->ordered($timezoneNames, $timezones, fn (object $timezone): array => [
                'name' => $timezone->name,
                'label' => $timezone->label,
            ]),
            'localities' => $localities->map(fn (object $locality): array => [
                'id' => (int) $locality->id,
                'name' => $locality->name,
                'administrativeArea' => $locality->administrative_area,
                'timezone' => $locality->timezone_name,
            ])->all(),
            'addressSchema' => $this->decodeJson($version->address_schema) ?? [],
        ];

        if ($includePackMetadata) {
            $result['packKey'] = (string) $pack->pack_key;
            $result['capabilities'] = $this->decodeJson($version->capabilities) ?? [];
        }

        return $result;
    }

    private function decodeJson(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            $values,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
    }

    /**
     * @param  list<string>  $keys
     * @param  Collection<string, object>  $records
     * @return list<array<string, mixed>>
     */
    private function ordered(array $keys, Collection $records, callable $serialize): array
    {
        return collect($keys)
            ->map(fn (string $key): ?array => $records->has($key) ? $serialize($records->get($key)) : null)
            ->filter()
            ->values()
            ->all();
    }
}
