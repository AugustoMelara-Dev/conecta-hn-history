<?php

declare(strict_types=1);

namespace App\Application\Markets;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class HondurasMarketBackfill
{
    public function run(bool $dryRun = false, int $limit = 0, int $offset = 0): HondurasMarketBackfillReport
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('The backfill limit and offset must be zero or greater.');
        }

        $operation = function () use ($limit, $offset): void {
            $now = now();

            DB::table('countries')->updateOrInsert(
                ['code' => 'HN'],
                [
                    'name' => 'Honduras',
                    'native_name' => 'Honduras',
                    'calling_code' => '+504',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
            DB::table('currencies')->updateOrInsert(
                ['code' => 'HNL'],
                [
                    'name' => 'Honduran lempira',
                    'symbol' => 'L',
                    'minor_unit' => 2,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
            DB::table('locales')->updateOrInsert(
                ['code' => 'es-HN'],
                [
                    'name' => 'Spanish (Honduras)',
                    'native_name' => 'Español (Honduras)',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
            DB::table('timezones')->updateOrInsert(
                ['name' => 'America/Tegucigalpa'],
                [
                    'label' => 'Central Time — Honduras',
                    'utc_offset' => '-06:00',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $marketPackId = (string) (
                DB::table('market_packs')->where('key', 'hn')->value('id')
                ?: Str::uuid()
            );
            DB::table('market_packs')->updateOrInsert(
                ['key' => 'hn'],
                [
                    'id' => $marketPackId,
                    'country_code' => 'HN',
                    'name' => 'Honduras',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $marketPackVersionId = (string) (
                DB::table('market_pack_versions')
                    ->where('market_pack_id', $marketPackId)
                    ->where('version', 1)
                    ->value('id')
                ?: Str::uuid()
            );

            if (! DB::table('market_pack_versions')->where('id', $marketPackVersionId)->exists()) {
                DB::table('market_pack_versions')->insert([
                    'id' => $marketPackVersionId,
                    'market_pack_id' => $marketPackId,
                    'version' => 1,
                    'status' => 'published',
                    'default_locale_code' => 'es-HN',
                    'default_currency_code' => 'HNL',
                    'default_timezone_name' => 'America/Tegucigalpa',
                    'supported_locales' => json_encode(['es-HN'], JSON_THROW_ON_ERROR),
                    'supported_currencies' => json_encode(['HNL'], JSON_THROW_ON_ERROR),
                    'supported_timezones' => json_encode(['America/Tegucigalpa'], JSON_THROW_ON_ERROR),
                    'capabilities' => json_encode([], JSON_THROW_ON_ERROR),
                    'address_schema' => json_encode([
                        'levels' => ['department', 'municipality', 'locality'],
                        'postal_code_required' => false,
                    ], JSON_THROW_ON_ERROR),
                    'effective_from' => $now,
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->mapAdministrativeAreas($now, $limit, $offset);
            $this->mapLocalities($now, $limit, $offset);
            $this->backfillTenants($marketPackVersionId, $now, $limit, $offset);
        };

        if ($dryRun) {
            DB::beginTransaction();

            try {
                $operation();
                $report = $this->report();
                DB::rollBack();

                return $report;
            } catch (\Throwable $exception) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                throw $exception;
            }
        }

        DB::transaction($operation);

        return $this->report();
    }

    private function mapAdministrativeAreas(mixed $now, int $limit, int $offset): void
    {
        $departments = DB::table('departments')->orderBy('id');
        $municipalities = DB::table('municipalities')->orderBy('id');

        if ($limit > 0) {
            $departments->offset($offset)->limit($limit);
            $municipalities->offset($offset)->limit($limit);
        } elseif ($offset > 0) {
            $departments->offset($offset);
            $municipalities->offset($offset);
        }

        $departments->each(function (object $department) use ($now): void {
            DB::table('administrative_areas')->updateOrInsert(
                ['legacy_department_id' => $department->id],
                [
                    'country_code' => 'HN',
                    'parent_id' => null,
                    'level' => 1,
                    'type' => 'department',
                    'code' => null,
                    'name' => $department->name,
                    'slug' => $department->slug,
                    'legacy_municipality_id' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        });

        $municipalities->each(function (object $municipality) use ($now): void {
            $parentId = DB::table('administrative_areas')
                ->where('legacy_department_id', $municipality->department_id)
                ->value('id');

            DB::table('administrative_areas')->updateOrInsert(
                ['legacy_municipality_id' => $municipality->id],
                [
                    'country_code' => 'HN',
                    'parent_id' => $parentId,
                    'level' => 2,
                    'type' => 'municipality',
                    'code' => null,
                    'name' => $municipality->name,
                    'slug' => $municipality->slug,
                    'legacy_department_id' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        });
    }

    private function mapLocalities(mixed $now, int $limit, int $offset): void
    {
        $cities = DB::table('cities')->orderBy('id');

        if ($limit > 0) {
            $cities->offset($offset)->limit($limit);
        } elseif ($offset > 0) {
            $cities->offset($offset);
        }

        $cities->each(function (object $city) use ($now): void {
            $areaId = $city->municipality_id === null
                ? null
                : DB::table('administrative_areas')
                    ->where('legacy_municipality_id', $city->municipality_id)
                    ->value('id');
            $coordinates = $this->decodeCoordinates($city->coordinates ?? null);

            DB::table('localities')->updateOrInsert(
                ['legacy_city_id' => $city->id],
                [
                    'country_code' => 'HN',
                    'administrative_area_id' => $areaId,
                    'name' => $city->name,
                    'slug' => $city->slug,
                    'timezone_name' => 'America/Tegucigalpa',
                    'latitude' => $coordinates['lat'] ?? null,
                    'longitude' => $coordinates['lng'] ?? null,
                    'is_active' => (bool) ($city->is_active ?? true),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        });
    }

    private function backfillTenants(string $marketPackVersionId, mixed $now, int $limit, int $offset): void
    {
        $tenants = DB::table('tenants')->orderBy('id');

        if ($limit > 0) {
            $tenants->offset($offset)->limit($limit);
        } elseif ($offset > 0) {
            $tenants->offset($offset);
        }

        $tenants->each(function (object $tenant) use ($marketPackVersionId, $now): void {
            if (! DB::table('tenant_markets')->where('tenant_id', $tenant->id)->exists()) {
                DB::table('tenant_markets')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'market_pack_version_id' => $marketPackVersionId,
                    'country_code' => 'HN',
                    'locale_code' => 'es-HN',
                    'currency_code' => 'HNL',
                    'timezone_name' => 'America/Tegucigalpa',
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($tenant->city_id === null || DB::table('locations')->where('tenant_id', $tenant->id)->exists()) {
                return;
            }

            $locality = DB::table('localities')
                ->where('legacy_city_id', $tenant->city_id)
                ->first();

            if ($locality === null) {
                return;
            }

            DB::table('locations')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'country_code' => 'HN',
                'administrative_area_id' => $locality->administrative_area_id,
                'locality_id' => $locality->id,
                'locale_code' => 'es-HN',
                'currency_code' => 'HNL',
                'timezone_name' => 'America/Tegucigalpa',
                'name' => $tenant->name,
                'slug' => 'primary',
                'address' => $tenant->address ?? null,
                'phone' => $tenant->phone ?? null,
                'contact_name' => null,
                'hours_data' => null,
                'is_active' => true,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * @return array{lat?: float|int|string, lng?: float|int|string}
     */
    private function decodeCoordinates(mixed $coordinates): array
    {
        if (is_array($coordinates)) {
            return $coordinates;
        }

        if (! is_string($coordinates) || $coordinates === '') {
            return [];
        }

        $decoded = json_decode($coordinates, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function report(): HondurasMarketBackfillReport
    {
        $source = [
            'departments' => DB::table('departments')->count(),
            'municipalities' => DB::table('municipalities')->count(),
            'cities' => DB::table('cities')->count(),
            'tenants' => DB::table('tenants')->count(),
        ];
        $mapped = [
            'departments' => DB::table('administrative_areas')->whereNotNull('legacy_department_id')->count(),
            'municipalities' => DB::table('administrative_areas')->whereNotNull('legacy_municipality_id')->count(),
            'cities' => DB::table('localities')->whereNotNull('legacy_city_id')->count(),
            'tenant_markets' => DB::table('tenant_markets')->where('country_code', 'HN')->count(),
            'locations' => DB::table('locations')->where('country_code', 'HN')->count(),
        ];
        $gaps = [
            'departments' => max(0, $source['departments'] - $mapped['departments']),
            'municipalities' => max(0, $source['municipalities'] - $mapped['municipalities']),
            'cities' => max(0, $source['cities'] - $mapped['cities']),
            'tenants_without_market' => DB::table('tenants')
                ->leftJoin('tenant_markets', 'tenant_markets.tenant_id', '=', 'tenants.id')
                ->whereNull('tenant_markets.id')
                ->count(),
            'tenants_with_city_without_location' => DB::table('tenants')
                ->leftJoin('locations', 'locations.tenant_id', '=', 'tenants.id')
                ->whereNotNull('tenants.city_id')
                ->whereNull('locations.id')
                ->count(),
            'tenants_without_legacy_city' => DB::table('tenants')->whereNull('city_id')->count(),
        ];

        return new HondurasMarketBackfillReport(
            source: $source,
            mapped: $mapped,
            gaps: $gaps,
            officialAdministrativeDatasetComplete: $source['departments'] === 18
                && $source['municipalities'] === 298,
        );
    }
}
