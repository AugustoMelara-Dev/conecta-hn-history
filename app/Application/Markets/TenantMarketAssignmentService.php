<?php

declare(strict_types=1);

namespace App\Application\Markets;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TenantMarketAssignmentService
{
    public function assign(
        string $tenantId,
        string $countryCode,
        string $localeCode,
        string $currencyCode,
        string $timezone,
        ?int $localityId,
        string $locationName,
        ?string $address,
    ): void {
        $countryCode = strtoupper($countryCode);
        $currencyCode = strtoupper($currencyCode);

        DB::transaction(function () use (
            $tenantId,
            $countryCode,
            $localeCode,
            $currencyCode,
            $timezone,
            $localityId,
            $locationName,
            $address,
        ): void {
            $version = DB::table('market_pack_versions as versions')
                ->join('market_packs as packs', 'packs.id', '=', 'versions.market_pack_id')
                ->join('countries', 'countries.code', '=', 'packs.country_code')
                ->where('packs.country_code', $countryCode)
                ->where('packs.is_active', true)
                ->where('countries.is_active', true)
                ->where('versions.status', 'published')
                ->whereNotNull('versions.published_at')
                ->where('versions.published_at', '<=', now())
                ->where(function ($query): void {
                    $query->whereNull('versions.effective_from')->orWhere('versions.effective_from', '<=', now());
                })
                ->orderByDesc('versions.version')
                ->select('versions.*')
                ->first();

            if ($version === null) {
                throw ValidationException::withMessages([
                    'country_code' => 'The selected market is not available.',
                ]);
            }

            $this->assertSupported('locale_code', $localeCode, $version->supported_locales);
            $this->assertSupported('currency_code', $currencyCode, $version->supported_currencies);
            $this->assertSupported('timezone', $timezone, $version->supported_timezones);
            $this->assertActiveReference('locales', 'code', $localeCode, 'locale_code');
            $this->assertActiveReference('currencies', 'code', $currencyCode, 'currency_code');
            $this->assertActiveReference('timezones', 'name', $timezone, 'timezone');

            $locality = $localityId === null
                ? null
                : DB::table('localities')
                    ->where('id', $localityId)
                    ->where('country_code', $countryCode)
                    ->where('is_active', true)
                    ->first();

            if ($localityId !== null && $locality === null) {
                throw ValidationException::withMessages([
                    'locality_id' => 'The selected locality does not belong to the selected market.',
                ]);
            }

            $now = now();
            $existingMarket = DB::table('tenant_markets')
                ->where('tenant_id', $tenantId)
                ->first(['id', 'created_at']);
            $marketId = (string) ($existingMarket?->id ?: Str::uuid());
            DB::table('tenant_markets')->updateOrInsert(
                ['tenant_id' => $tenantId],
                [
                    'id' => $marketId,
                    'market_pack_version_id' => $version->id,
                    'country_code' => $countryCode,
                    'locale_code' => $localeCode,
                    'currency_code' => $currencyCode,
                    'timezone_name' => $timezone,
                    'is_primary' => true,
                    'created_at' => $existingMarket?->created_at ?? $now,
                    'updated_at' => $now,
                ],
            );

            if ($locality === null) {
                return;
            }

            $existingLocation = DB::table('locations')
                ->where('tenant_id', $tenantId)
                ->where('is_primary', true)
                ->first(['id', 'created_at']);
            $locationId = (string) ($existingLocation?->id ?: Str::uuid());
            DB::table('locations')->updateOrInsert(
                ['id' => $locationId],
                [
                    'tenant_id' => $tenantId,
                    'country_code' => $countryCode,
                    'administrative_area_id' => $locality->administrative_area_id,
                    'locality_id' => $locality->id,
                    'locale_code' => $localeCode,
                    'currency_code' => $currencyCode,
                    'timezone_name' => $timezone,
                    'name' => $locationName,
                    'slug' => 'primary',
                    'address' => $address,
                    'phone' => null,
                    'contact_name' => null,
                    'hours_data' => null,
                    'is_active' => true,
                    'is_primary' => true,
                    'created_at' => $existingLocation?->created_at ?? $now,
                    'updated_at' => $now,
                ],
            );
        });
    }

    private function assertSupported(string $field, string $value, mixed $encodedValues): void
    {
        $supported = json_decode((string) $encodedValues, true);

        if (! is_array($supported) || ! in_array($value, $supported, true)) {
            throw ValidationException::withMessages([
                $field => 'The selected value is not supported by this Market Pack.',
            ]);
        }
    }

    private function assertActiveReference(string $table, string $column, string $value, string $field): void
    {
        if (! DB::table($table)->where($column, $value)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                $field => 'The selected value is not active for registration.',
            ]);
        }
    }
}
