<?php

declare(strict_types=1);

namespace Tests\Feature\Markets;

use App\Application\Markets\TenantMarketAssignmentService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TenantMarketAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_a_non_honduras_market_without_a_legacy_city(): void
    {
        $market = $this->seedCanadianMarket();
        $tenant = Tenant::factory()->create(['city_id' => null]);

        app(TenantMarketAssignmentService::class)->assign(
            tenantId: (string) $tenant->id,
            countryCode: 'CA',
            localeCode: 'en-CA',
            currencyCode: 'CAD',
            timezone: 'America/Toronto',
            localityId: $market['locality_id'],
            locationName: 'Toronto office',
            address: '100 King Street West',
        );

        $this->assertDatabaseHas('tenant_markets', [
            'tenant_id' => $tenant->id,
            'country_code' => 'CA',
            'locale_code' => 'en-CA',
            'currency_code' => 'CAD',
            'timezone_name' => 'America/Toronto',
        ]);
        $this->assertDatabaseHas('locations', [
            'tenant_id' => $tenant->id,
            'country_code' => 'CA',
            'locality_id' => $market['locality_id'],
            'address' => '100 King Street West',
        ]);
        self::assertNull($tenant->refresh()->city_id);
    }

    public function test_it_rejects_a_locality_from_another_country(): void
    {
        $market = $this->seedCanadianMarket();
        $tenant = Tenant::factory()->create(['city_id' => null]);
        DB::table('countries')->insert([
            'code' => 'HN',
            'name' => 'Honduras',
            'native_name' => 'Honduras',
            'calling_code' => '+504',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('localities')->where('id', $market['locality_id'])->update(['country_code' => 'HN']);

        $this->expectException(ValidationException::class);

        app(TenantMarketAssignmentService::class)->assign(
            tenantId: (string) $tenant->id,
            countryCode: 'CA',
            localeCode: 'en-CA',
            currencyCode: 'CAD',
            timezone: 'America/Toronto',
            localityId: $market['locality_id'],
            locationName: 'Toronto office',
            address: null,
        );
    }

    public function test_it_rejects_a_market_pack_version_before_its_publication_and_effective_time(): void
    {
        $market = $this->seedCanadianMarket();
        $tenant = Tenant::factory()->create(['city_id' => null]);
        DB::table('market_pack_versions')->update([
            'published_at' => now()->addDay(),
            'effective_from' => now()->addDay(),
        ]);

        $this->expectException(ValidationException::class);

        app(TenantMarketAssignmentService::class)->assign(
            tenantId: (string) $tenant->id,
            countryCode: 'CA',
            localeCode: 'en-CA',
            currencyCode: 'CAD',
            timezone: 'America/Toronto',
            localityId: $market['locality_id'],
            locationName: 'Toronto office',
            address: null,
        );
    }

    /**
     * @return array{locality_id: int}
     */
    private function seedCanadianMarket(): array
    {
        $now = now();
        DB::table('countries')->insert([
            'code' => 'CA',
            'name' => 'Canada',
            'native_name' => 'Canada',
            'calling_code' => '+1',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('currencies')->insert([
            'code' => 'CAD',
            'name' => 'Canadian dollar',
            'symbol' => '$',
            'minor_unit' => 2,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('locales')->insert([
            'code' => 'en-CA',
            'name' => 'English (Canada)',
            'native_name' => 'English (Canada)',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('timezones')->insert([
            'name' => 'America/Toronto',
            'label' => 'Eastern Time — Toronto',
            'utc_offset' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $packId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        DB::table('market_packs')->insert([
            'id' => $packId,
            'key' => 'ca',
            'country_code' => 'CA',
            'name' => 'Canada',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('market_pack_versions')->insert([
            'id' => $versionId,
            'market_pack_id' => $packId,
            'version' => 1,
            'status' => 'published',
            'default_locale_code' => 'en-CA',
            'default_currency_code' => 'CAD',
            'default_timezone_name' => 'America/Toronto',
            'supported_locales' => json_encode(['en-CA'], JSON_THROW_ON_ERROR),
            'supported_currencies' => json_encode(['CAD'], JSON_THROW_ON_ERROR),
            'supported_timezones' => json_encode(['America/Toronto'], JSON_THROW_ON_ERROR),
            'capabilities' => json_encode([], JSON_THROW_ON_ERROR),
            'address_schema' => json_encode(['levels' => ['province', 'city']], JSON_THROW_ON_ERROR),
            'effective_from' => $now,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $areaId = DB::table('administrative_areas')->insertGetId([
            'country_code' => 'CA',
            'parent_id' => null,
            'level' => 1,
            'type' => 'province',
            'code' => 'ON',
            'name' => 'Ontario',
            'slug' => 'ontario',
            'legacy_department_id' => null,
            'legacy_municipality_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $localityId = DB::table('localities')->insertGetId([
            'country_code' => 'CA',
            'administrative_area_id' => $areaId,
            'name' => 'Toronto',
            'slug' => 'toronto',
            'timezone_name' => 'America/Toronto',
            'latitude' => 43.6532,
            'longitude' => -79.3832,
            'legacy_city_id' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['locality_id' => $localityId];
    }
}
