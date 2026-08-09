<?php

declare(strict_types=1);

namespace Tests\Feature\Geography;

use App\Application\Geography\Contracts\TenantCoverageWriter;
use App\Application\Geography\ServiceCoverageResolver;
use App\Domain\Geography\Enums\FulfillmentMode;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TenantCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_and_resolver_enforce_exact_tenant_and_global_geography(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        [$parentId, $childId, $localityId] = $this->geography();
        $locationA = $this->location((string) $tenantA->getKey(), $childId, $localityId, 'principal-a');
        $locationB = $this->location((string) $tenantB->getKey(), $childId, $localityId, 'principal-b');

        $writer = app(TenantCoverageWriter::class);
        $writer->replace((string) $tenantA->getKey(), $locationA, [
            FulfillmentMode::Delivery,
            FulfillmentMode::Pickup,
            FulfillmentMode::Remote,
        ], 'administrative_area');

        self::assertDatabaseCount('location_service_areas', 3);
        self::assertDatabaseMissing('location_service_areas', ['tenant_id' => (string) $tenantB->getKey()]);

        $resolver = app(ServiceCoverageResolver::class);
        self::assertTrue($resolver->covers((string) $tenantA->getKey(), 'HN', $childId, $localityId, FulfillmentMode::Delivery));
        self::assertTrue($resolver->covers((string) $tenantA->getKey(), 'HN', $childId, $localityId, FulfillmentMode::Pickup));
        self::assertTrue($resolver->covers((string) $tenantA->getKey(), 'HN', $parentId, null, FulfillmentMode::Remote));
        self::assertFalse($resolver->covers((string) $tenantB->getKey(), 'HN', $childId, $localityId, FulfillmentMode::Delivery));

        $this->expectException(ValidationException::class);
        $writer->replace((string) $tenantA->getKey(), $locationB, [FulfillmentMode::Delivery], 'locality');
    }

    public function test_radius_requires_official_coordinates_and_uses_real_distance(): void
    {
        $tenant = Tenant::factory()->create();
        [, $areaId, $localityId] = $this->geography();
        $location = $this->location((string) $tenant->getKey(), $areaId, $localityId, 'radio');
        app(TenantCoverageWriter::class)->replace(
            (string) $tenant->getKey(), $location, [FulfillmentMode::OnSite], 'radius', 15,
        );

        $resolver = app(ServiceCoverageResolver::class);
        self::assertTrue($resolver->covers((string) $tenant->getKey(), 'HN', $areaId, $localityId, FulfillmentMode::OnSite, 14.09, -87.20));
        self::assertFalse($resolver->covers((string) $tenant->getKey(), 'HN', $areaId, $localityId, FulfillmentMode::OnSite, 15.50, -88.00));
    }

    /** @return array{int,int,int} */
    private function geography(): array
    {
        $now = now();
        DB::table('countries')->insert(['code' => 'HN', 'name' => 'Honduras', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('currencies')->insert(['code' => 'HNL', 'name' => 'Lempira', 'symbol' => 'L', 'minor_unit' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('locales')->insert(['code' => 'es-HN', 'name' => 'Espanol Honduras', 'native_name' => 'Espanol', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('timezones')->insert(['name' => 'America/Tegucigalpa', 'label' => 'Tegucigalpa', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $parent = DB::table('administrative_areas')->insertGetId(['country_code' => 'HN', 'parent_id' => null, 'level' => 1, 'type' => 'department', 'code' => 'FM', 'name' => 'Francisco Morazan', 'slug' => 'francisco-morazan', 'created_at' => $now, 'updated_at' => $now]);
        $child = DB::table('administrative_areas')->insertGetId(['country_code' => 'HN', 'parent_id' => $parent, 'level' => 2, 'type' => 'municipality', 'code' => 'TGU', 'name' => 'Distrito Central', 'slug' => 'distrito-central', 'created_at' => $now, 'updated_at' => $now]);
        $locality = DB::table('localities')->insertGetId(['country_code' => 'HN', 'administrative_area_id' => $child, 'name' => 'Tegucigalpa', 'slug' => 'tegucigalpa', 'timezone_name' => 'America/Tegucigalpa', 'latitude' => 14.0723, 'longitude' => -87.1921, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);

        return [$parent, $child, $locality];
    }

    private function location(string $tenantId, int $areaId, int $localityId, string $slug): string
    {
        $id = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $id, 'tenant_id' => $tenantId, 'country_code' => 'HN',
            'administrative_area_id' => $areaId, 'locality_id' => $localityId,
            'locale_code' => 'es-HN', 'currency_code' => 'HNL', 'timezone_name' => 'America/Tegucigalpa',
            'name' => $slug, 'slug' => $slug, 'is_active' => true, 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }
}
