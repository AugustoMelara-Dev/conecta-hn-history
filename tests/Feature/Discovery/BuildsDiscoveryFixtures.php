<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait BuildsDiscoveryFixtures
{
    protected function assignMarket(
        Tenant $tenant,
        string $countryCode,
        string $countryName,
        string $currencyCode,
        string $currencyName,
        string $currencySymbol,
        string $localeCode,
        string $localeName,
        string $timezoneName,
        string $timezoneLabel,
        string $marketKey,
        string $locationName,
        string $address,
        array $addressLevels,
    ): string {
        $now = now();

        DB::table('countries')->updateOrInsert(
            ['code' => $countryCode],
            [
                'name' => $countryName,
                'native_name' => $countryName,
                'calling_code' => '+0',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('currencies')->updateOrInsert(
            ['code' => $currencyCode],
            [
                'name' => $currencyName,
                'symbol' => $currencySymbol,
                'minor_unit' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('locales')->updateOrInsert(
            ['code' => $localeCode],
            [
                'name' => $localeName,
                'native_name' => $localeName,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('timezones')->updateOrInsert(
            ['name' => $timezoneName],
            [
                'label' => $timezoneLabel,
                'utc_offset' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $packId = (string) (DB::table('market_packs')->where('key', $marketKey)->value('id') ?: Str::uuid());

        DB::table('market_packs')->updateOrInsert(
            ['key' => $marketKey],
            [
                'id' => $packId,
                'country_code' => $countryCode,
                'name' => $countryName,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $versionId = (string) (DB::table('market_pack_versions')
            ->where('market_pack_id', $packId)
            ->where('version', 1)
            ->value('id') ?: Str::uuid());

        DB::table('market_pack_versions')->updateOrInsert(
            ['market_pack_id' => $packId, 'version' => 1],
            [
                'id' => $versionId,
                'status' => 'published',
                'default_locale_code' => $localeCode,
                'default_currency_code' => $currencyCode,
                'default_timezone_name' => $timezoneName,
                'supported_locales' => json_encode([$localeCode], JSON_THROW_ON_ERROR),
                'supported_currencies' => json_encode([$currencyCode], JSON_THROW_ON_ERROR),
                'supported_timezones' => json_encode([$timezoneName], JSON_THROW_ON_ERROR),
                'capabilities' => json_encode([], JSON_THROW_ON_ERROR),
                'address_schema' => json_encode(['levels' => $addressLevels], JSON_THROW_ON_ERROR),
                'effective_from' => $now,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('tenant_markets')->updateOrInsert(
            ['tenant_id' => $tenant->id],
            [
                'id' => (string) (DB::table('tenant_markets')->where('tenant_id', $tenant->id)->value('id') ?: Str::uuid()),
                'market_pack_version_id' => $versionId,
                'country_code' => $countryCode,
                'locale_code' => $localeCode,
                'currency_code' => $currencyCode,
                'timezone_name' => $timezoneName,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $locationId = (string) Str::uuid();

        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'country_code' => $countryCode,
            'administrative_area_id' => null,
            'locality_id' => null,
            'locale_code' => $localeCode,
            'currency_code' => $currencyCode,
            'timezone_name' => $timezoneName,
            'name' => $locationName,
            'slug' => Str::slug($locationName).'-'.Str::lower(Str::random(6)),
            'address' => $address,
            'phone' => null,
            'contact_name' => null,
            'hours_data' => null,
            'is_active' => true,
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $locationId;
    }

    protected function assignHonduras(Tenant $tenant, string $locationName = 'Tegucigalpa'): string
    {
        return $this->assignMarket(
            tenant: $tenant,
            countryCode: 'HN',
            countryName: 'Honduras',
            currencyCode: 'HNL',
            currencyName: 'Lempira',
            currencySymbol: 'L',
            localeCode: 'es-HN',
            localeName: 'Español (Honduras)',
            timezoneName: 'America/Tegucigalpa',
            timezoneLabel: 'Central America — Tegucigalpa',
            marketKey: 'hn',
            locationName: $locationName,
            address: 'Boulevard Centroamérica',
            addressLevels: ['department', 'city'],
        );
    }

    protected function assignCanada(Tenant $tenant, string $locationName = 'Toronto'): string
    {
        return $this->assignMarket(
            tenant: $tenant,
            countryCode: 'CA',
            countryName: 'Canada',
            currencyCode: 'CAD',
            currencyName: 'Canadian Dollar',
            currencySymbol: '$',
            localeCode: 'en-CA',
            localeName: 'English (Canada)',
            timezoneName: 'America/Toronto',
            timezoneLabel: 'Eastern Time — Toronto',
            marketKey: 'ca',
            locationName: $locationName,
            address: '100 King Street',
            addressLevels: ['province', 'city'],
        );
    }

    protected function attachServiceToLocation(Tenant $tenant, int $serviceId, string $locationId): void
    {
        DB::table('service_locations')->insert([
            'tenant_id' => $tenant->id,
            'service_id' => $serviceId,
            'location_id' => $locationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createAvailabilityRule(
        Tenant $tenant,
        string $locationId,
        int $serviceId,
        string $timezoneName,
        string $localDate,
        string $startsAt = '09:00',
        string $endsAt = '17:00',
    ): void {
        $date = CarbonImmutable::parse($localDate);

        DB::table('availability_rules')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'location_id' => $locationId,
            'service_id' => $serviceId,
            'service_provider_id' => null,
            'weekday' => $date->dayOfWeek,
            'starts_at_local' => $startsAt,
            'ends_at_local' => $endsAt,
            'timezone' => $timezoneName,
            'effective_from' => $date->toDateString(),
            'effective_until' => $date->toDateString(),
            'is_active' => true,
            'source' => 'canonical',
            'source_key' => 'availability-rule-'.Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createClosedException(
        Tenant $tenant,
        string $locationId,
        int $serviceId,
        string $timezoneName,
        string $localDate,
        string $reason = 'merchant_unavailable',
    ): void {
        DB::table('availability_exceptions')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'location_id' => $locationId,
            'service_id' => $serviceId,
            'service_provider_id' => null,
            'local_date' => $localDate,
            'kind' => 'closed',
            'starts_at_local' => null,
            'ends_at_local' => null,
            'timezone' => $timezoneName,
            'reason' => $reason,
            'source' => 'canonical',
            'source_key' => 'availability-exception-'.Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
