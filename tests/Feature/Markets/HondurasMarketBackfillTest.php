<?php

declare(strict_types=1);

namespace Tests\Feature\Markets;

use App\Application\Markets\HondurasMarketBackfill;
use App\Models\City;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class HondurasMarketBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_maps_legacy_honduras_geography_and_tenant_without_changing_ids(): void
    {
        $department = Department::factory()->create([
            'name' => 'Cortés',
            'slug' => 'cortes',
        ]);
        $municipality = Municipality::factory()->create([
            'department_id' => $department->id,
            'name' => 'San Pedro Sula',
            'slug' => 'san-pedro-sula',
        ]);
        $city = City::factory()->create([
            'municipality_id' => $municipality->id,
            'name' => 'San Pedro Sula',
            'slug' => 'san-pedro-sula',
            'coordinates' => ['lat' => 15.5045, 'lng' => -88.0251],
        ]);
        $tenant = Tenant::factory()->create([
            'city_id' => $city->id,
            'address' => 'Barrio Río de Piedras',
        ]);

        $report = app(HondurasMarketBackfill::class)->run();

        $this->assertDatabaseHas('countries', ['code' => 'HN', 'is_active' => true]);
        $this->assertDatabaseHas('currencies', ['code' => 'HNL', 'minor_unit' => 2]);
        $this->assertDatabaseHas('locales', ['code' => 'es-HN']);
        $this->assertDatabaseHas('timezones', ['name' => 'America/Tegucigalpa']);
        $this->assertDatabaseHas('market_packs', ['key' => 'hn', 'country_code' => 'HN']);

        $departmentArea = DB::table('administrative_areas')
            ->where('legacy_department_id', $department->id)
            ->first();
        self::assertNotNull($departmentArea);
        self::assertSame('department', $departmentArea->type);

        $municipalityArea = DB::table('administrative_areas')
            ->where('legacy_municipality_id', $municipality->id)
            ->first();
        self::assertNotNull($municipalityArea);
        self::assertSame((int) $departmentArea->id, (int) $municipalityArea->parent_id);

        $locality = DB::table('localities')
            ->where('legacy_city_id', $city->id)
            ->first();
        self::assertNotNull($locality);
        self::assertSame((int) $municipalityArea->id, (int) $locality->administrative_area_id);

        $this->assertDatabaseHas('tenant_markets', [
            'tenant_id' => $tenant->id,
            'country_code' => 'HN',
            'locale_code' => 'es-HN',
            'currency_code' => 'HNL',
            'timezone_name' => 'America/Tegucigalpa',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('locations', [
            'tenant_id' => $tenant->id,
            'locality_id' => $locality->id,
            'country_code' => 'HN',
            'currency_code' => 'HNL',
            'timezone_name' => 'America/Tegucigalpa',
            'address' => 'Barrio Río de Piedras',
            'is_primary' => true,
        ]);

        self::assertSame($department->id, Department::query()->whereKey($department->id)->value('id'));
        self::assertSame($municipality->id, Municipality::query()->whereKey($municipality->id)->value('id'));
        self::assertSame($city->id, City::query()->whereKey($city->id)->value('id'));
        self::assertSame(1, $report->source['departments']);
        self::assertSame(1, $report->mapped['municipalities']);
        self::assertSame(0, $report->gaps['cities']);
        self::assertFalse($report->officialAdministrativeDatasetComplete);
    }

    public function test_backfill_is_idempotent_and_does_not_invent_a_locality(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => null]);
        $backfill = app(HondurasMarketBackfill::class);

        $firstReport = $backfill->run();
        $firstCounts = $this->marketCounts();
        $backfill->run();

        self::assertSame($firstCounts, $this->marketCounts());
        $this->assertDatabaseHas('tenant_markets', [
            'tenant_id' => $tenant->id,
            'country_code' => 'HN',
        ]);
        $this->assertDatabaseMissing('locations', [
            'tenant_id' => $tenant->id,
        ]);

        $capabilities = DB::table('market_pack_versions')->value('capabilities');
        self::assertSame([], json_decode((string) $capabilities, true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(1, $firstReport->gaps['tenants_without_legacy_city']);
        self::assertSame(0, $firstReport->gaps['tenants_without_market']);
    }

    /**
     * @return array<string, int>
     */
    private function marketCounts(): array
    {
        return [
            'countries' => DB::table('countries')->count(),
            'currencies' => DB::table('currencies')->count(),
            'locales' => DB::table('locales')->count(),
            'timezones' => DB::table('timezones')->count(),
            'market_packs' => DB::table('market_packs')->count(),
            'market_pack_versions' => DB::table('market_pack_versions')->count(),
            'administrative_areas' => DB::table('administrative_areas')->count(),
            'localities' => DB::table('localities')->count(),
            'tenant_markets' => DB::table('tenant_markets')->count(),
            'locations' => DB::table('locations')->count(),
        ];
    }
}
