<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Application\Discovery\DiscoveryQueryService;
use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Domain\Discovery\DiscoveryQuery;
use App\Infrastructure\Discovery\DiscoveryProjectionStore;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DiscoveryOfferDimensionsTest extends TestCase
{
    use BuildsDiscoveryFixtures;
    use RefreshDatabase;

    public function test_schema_has_category_and_location_dimensions_with_named_constraints(): void
    {
        self::assertTrue(Schema::hasColumn('discovery_published_offers', 'business_category_id'));
        self::assertTrue(Schema::hasColumn('discovery_published_offers', 'business_category_slug'));
        self::assertTrue(Schema::hasTable('discovery_offer_locations'));

        $indexes = collect(Schema::getIndexes('discovery_offer_locations'))
            ->pluck('name')
            ->all();

        self::assertContains('discover_offer_location_unique', $indexes);
        self::assertContains('discover_offer_locality_lookup_idx', $indexes);
        self::assertContains('discover_offer_location_tenant_idx', $indexes);
        self::assertContains('discover_category_lookup_idx', collect(Schema::getIndexes('discovery_published_offers'))->pluck('name')->all());
    }

    public function test_product_and_service_use_active_tenant_category_and_ignore_offer_category(): void
    {
        $tenantCategory = $this->category('coffee', true);
        $conflictingCategory = $this->category('conflicting', true);
        $tenant = Tenant::factory()->create([
            'category_id' => $tenantCategory,
            'subscription_status' => 'active',
            'verification_status' => 'verified',
        ]);
        $this->assignHonduras($tenant);

        $product = Product::factory()->forTenant($tenant)->create([
            'category_id' => $conflictingCategory,
            'name' => 'Tenant category product',
            'currency' => 'HNL',
            'price_minor' => 1000,
            'is_active' => true,
            'is_visible' => true,
        ]);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Tenant category service',
            'currency' => 'HNL',
            'price_minor' => 2000,
            'is_active' => true,
        ]);
        $locationId = (string) DB::table('locations')->where('tenant_id', $tenant->id)->value('id');
        $this->attachServiceToLocation($tenant, $service->id, $locationId);
        $this->createAvailabilityRule($tenant, $locationId, $service->id, 'America/Tegucigalpa', '2026-08-05');

        app(PublishedCommercialProjectionService::class)->projectCurrent();

        foreach (['product:'.$product->id, 'service:'.$service->id] as $key) {
            $projection = DB::table('discovery_published_offers')->where('projection_key', $key)->sole();
            self::assertSame($tenantCategory, (int) $projection->business_category_id);
            self::assertSame('coffee', $projection->business_category_slug);
        }
    }

    public function test_duplicate_primary_product_locations_choose_ascending_uuid(): void
    {
        $tenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $this->assignHonduras($tenant);
        $existing = DB::table('locations')->where('tenant_id', $tenant->id)->first();
        $lowerId = '00000000-0000-0000-0000-000000000001';
        DB::table('locations')->insert(array_merge((array) $existing, [
            'id' => $lowerId,
            'slug' => 'lower-primary',
            'name' => 'Lower primary',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        DB::table('locations')->where('id', $existing->id)->update(['is_primary' => true]);

        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Duplicate primary offer',
            'currency' => 'HNL',
            'price_minor' => 1000,
            'is_active' => true,
            'is_visible' => true,
        ]);
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $projection = DB::table('discovery_published_offers')->where('projection_key', 'product:'.$product->id)->sole();
        self::assertSame($lowerId, $projection->location_id);
        self::assertSame(1, DB::table('discovery_offer_locations')->where('projection_id', $projection->id)->count());
    }

    public function test_two_location_service_has_one_stable_projection_and_two_dimensions(): void
    {
        [$tenant, $service] = $this->serviceWithLocations(['Tegucigalpa', 'San Pedro Sula']);
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $projection = DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->sole();
        self::assertSame(1, DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->count());
        self::assertSame(2, DB::table('discovery_offer_locations')->where('projection_id', $projection->id)->count());
        self::assertSame((string) $tenant->id, (string) DB::table('discovery_offer_locations')->where('projection_id', $projection->id)->value('source_tenant_id'));
    }

    public function test_service_is_discoverable_in_each_eligible_city(): void
    {
        [, $service, $citySlugs] = $this->serviceWithLocations(['Tegucigalpa', 'San Pedro Sula'], true);
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $query = app(DiscoveryQueryService::class);
        $projectionId = (string) DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->value('id');
        foreach ($citySlugs as $slug) {
            $results = $query->search(new DiscoveryQuery('service', 'HN', null, 'HNL', null, null, 6, $slug))->results;
            self::assertContains($projectionId, array_column($results, 'projection_id'));
        }
    }

    public function test_cross_country_locality_is_not_hydrated_or_city_matchable(): void
    {
        $tenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $locationId = $this->assignHonduras($tenant);
        $this->assignCanada(Tenant::factory()->create(['subscription_status' => 'active']));
        $foreignLocality = $this->locality('Foreign city', 'foreign-city', true, 'CA', 'America/Toronto');
        DB::table('locations')->where('id', $locationId)->update(['locality_id' => $foreignLocality]);

        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Cross country product',
            'currency' => 'HNL',
            'price_minor' => 1000,
            'is_active' => true,
            'is_visible' => true,
        ]);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cross country service',
            'currency' => 'HNL',
            'price_minor' => 1000,
            'is_active' => true,
        ]);
        $this->attachServiceToLocation($tenant, $service->id, $locationId);
        $this->createAvailabilityRule($tenant, $locationId, $service->id, 'America/Tegucigalpa', '2026-08-05');

        app(PublishedCommercialProjectionService::class)->projectCurrent();

        foreach (['product:'.$product->id, 'service:'.$service->id] as $key) {
            $projection = DB::table('discovery_published_offers')->where('projection_key', $key)->sole();
            $dimension = DB::table('discovery_offer_locations')->where('projection_id', $projection->id)->sole();
            self::assertNull($dimension->locality_id);
            self::assertNull($dimension->locality_slug);
        }

        $results = app(DiscoveryQueryService::class)
            ->search(new DiscoveryQuery('country', 'HN', null, 'HNL', null, null, 6, 'foreign-city'))
            ->results;
        self::assertSame([], $results);
    }

    public function test_unavailable_secondary_city_does_not_match_when_another_location_makes_service_eligible(): void
    {
        [, $service, $citySlugs] = $this->serviceWithLocations(['Tegucigalpa', 'San Pedro Sula'], false, false);
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $query = app(DiscoveryQueryService::class);
        $results = $query->search(new DiscoveryQuery('service', 'HN', null, 'HNL', null, null, 6, $citySlugs[1]))->results;
        $projectionId = (string) DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->value('id');
        self::assertNotContains($projectionId, array_column($results, 'projection_id'));
    }

    public function test_desired_date_checks_all_same_city_locations_and_never_falls_back_to_base(): void
    {
        [$tenant, $service, $citySlugs, $locationIds] = $this->serviceWithSameLocality();
        $this->createClosedException($tenant, $locationIds[0], $service->id, 'America/Tegucigalpa', '2026-08-05');
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $query = app(DiscoveryQueryService::class);
        $projectionId = (string) DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->value('id');
        $results = $query->search(new DiscoveryQuery('Multi-city service', 'HN', null, 'HNL', '2026-08-05', null, 6, $citySlugs[0]))->results;
        self::assertContains($projectionId, array_column($results, 'projection_id'));

        $this->createClosedException($tenant, $locationIds[1], $service->id, 'America/Tegucigalpa', '2026-08-05');
        $results = $query->search(new DiscoveryQuery('Multi-city service', 'HN', null, 'HNL', '2026-08-05', null, 6, $citySlugs[0]))->results;
        self::assertNotContains($projectionId, array_column($results, 'projection_id'));
    }

    public function test_city_query_with_no_hydrated_dimension_skips_legacy_base_location(): void
    {
        [, $service, $citySlugs] = $this->serviceWithLocations(['Tegucigalpa', 'San Pedro Sula'], true);
        app(PublishedCommercialProjectionService::class)->projectCurrent();
        $projectionId = (string) DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->value('id');
        DB::table('discovery_offer_locations')->where('projection_id', $projectionId)->delete();

        $results = app(DiscoveryQueryService::class)
            ->search(new DiscoveryQuery('Multi-city service', 'HN', null, 'HNL', null, null, 6, $citySlugs[0]))
            ->results;
        self::assertSame([], $results);
    }

    public function test_store_drops_city_row_when_dimension_disappears_before_hydration(): void
    {
        [, $service, $citySlugs] = $this->serviceWithLocations(['Tegucigalpa', 'San Pedro Sula'], true);
        app(PublishedCommercialProjectionService::class)->projectCurrent();
        $projectionId = (string) DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->value('id');
        $mutated = false;

        DB::listen(function (QueryExecuted $query) use (&$mutated, $projectionId): void {
            $sql = strtolower(str_replace(['"', '`'], '', $query->sql));
            if ($mutated || ! str_contains($sql, 'from discovery_published_offers') || ! str_contains($sql, 'discovery_offer_locations')) {
                return;
            }

            $mutated = true;
            DB::table('discovery_offer_locations')
                ->where('projection_id', $projectionId)
                ->update(['locality_id' => null, 'locality_slug' => null]);
        });

        $rows = app(DiscoveryProjectionStore::class)->searchEligible(
            countryCode: 'HN',
            currencyCode: 'HNL',
            budgetMinor: null,
            offerType: 'service',
            localitySlug: $citySlugs[0],
        );

        self::assertTrue($mutated);
        self::assertSame([], $rows);
    }

    public function test_cross_tenant_service_location_links_never_project(): void
    {
        $tenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $this->assignHonduras($tenant);
        $otherTenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $otherLocation = $this->assignHonduras($otherTenant, 'Other tenant');
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'currency' => 'HNL', 'price_minor' => 1000, 'is_active' => true]);
        DB::table('service_locations')->insert([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'location_id' => $otherLocation,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PublishedCommercialProjectionService::class)->projectCurrent();
        $projection = DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->sole();
        self::assertSame(0, DB::table('discovery_offer_locations')->where('projection_id', $projection->id)->count());
    }

    public function test_null_or_inactive_dimensions_fail_closed_only_when_filter_requested(): void
    {
        $categoryId = $this->category('inactive-category', false);
        $tenant = Tenant::factory()->create(['category_id' => $categoryId, 'subscription_status' => 'active']);
        $this->assignHonduras($tenant);
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'Unfiltered offer', 'currency' => 'HNL', 'price_minor' => 1000, 'is_active' => true, 'is_visible' => true]);
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $query = app(DiscoveryQueryService::class);
        $projectionId = (string) DB::table('discovery_published_offers')->where('projection_key', 'product:'.$product->id)->value('id');
        self::assertContains($projectionId, array_column($query->search(new DiscoveryQuery('offer', 'HN', null, 'HNL'))->results, 'projection_id'));
        self::assertNotContains($projectionId, array_column($query->search(new DiscoveryQuery('offer', 'HN', null, 'HNL', null, null, 6, null, 'inactive-category'))->results, 'projection_id'));
    }

    public function test_null_or_inactive_locality_cannot_match_city_but_remains_in_unfiltered_projection(): void
    {
        $tenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $locationId = $this->assignHonduras($tenant);
        $localityId = $this->locality('Inactive city', 'inactive-city', false);
        DB::table('locations')->where('id', $locationId)->update(['locality_id' => $localityId]);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Inactive locality offer',
            'currency' => 'HNL',
            'price_minor' => 1000,
            'is_active' => true,
            'is_visible' => true,
        ]);
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $projection = DB::table('discovery_published_offers')->where('projection_key', 'product:'.$product->id)->sole();
        $dimension = DB::table('discovery_offer_locations')->where('projection_id', $projection->id)->sole();
        self::assertNull($dimension->locality_id);
        self::assertNull($dimension->locality_slug);
        $query = app(DiscoveryQueryService::class);
        self::assertContains($projection->id, array_column($query->search(new DiscoveryQuery('Inactive locality offer', 'HN', null, 'HNL'))->results, 'projection_id'));
        self::assertSame([], $query->search(new DiscoveryQuery('Inactive locality offer', 'HN', null, 'HNL', null, null, 6, 'inactive-city'))->results);
    }

    public function test_deleted_category_and_locality_null_foreign_keys_and_clear_stale_slugs(): void
    {
        $categoryId = $this->category('temporary-category', true);
        $tenant = Tenant::factory()->create(['category_id' => $categoryId, 'subscription_status' => 'active']);
        $locationId = $this->assignHonduras($tenant);
        $localityId = $this->locality('Temporary city', 'temporary-city', true);
        DB::table('locations')->where('id', $locationId)->update(['locality_id' => $localityId]);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Temporary offer',
            'currency' => 'HNL',
            'price_minor' => 1000,
            'is_active' => true,
            'is_visible' => true,
        ]);
        app(PublishedCommercialProjectionService::class)->projectCurrent();
        $projectionId = (string) DB::table('discovery_published_offers')->where('projection_key', 'product:'.$product->id)->value('id');
        self::assertSame('temporary-city', DB::table('discovery_offer_locations')->where('projection_id', $projectionId)->value('locality_slug'));

        DB::table('categories')->where('id', $categoryId)->delete();
        DB::table('localities')->where('id', $localityId)->delete();
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $projection = DB::table('discovery_published_offers')->where('id', $projectionId)->sole();
        $dimension = DB::table('discovery_offer_locations')->where('projection_id', $projectionId)->sole();
        self::assertNull($projection->business_category_id);
        self::assertNull($projection->business_category_slug);
        self::assertNull($dimension->locality_id);
        self::assertNull($dimension->locality_slug);
        self::assertSame([], app(DiscoveryQueryService::class)->search(new DiscoveryQuery('Temporary offer', 'HN', null, 'HNL', null, null, 6, 'temporary-city'))->results);
    }

    public function test_city_and_category_constraints_are_combined_with_and(): void
    {
        $categoryId = $this->category('coffee', true);
        $otherCategoryId = $this->category('bakery', true);
        $tenant = Tenant::factory()->create(['category_id' => $categoryId, 'subscription_status' => 'active']);
        $locationId = $this->assignHonduras($tenant);
        $locality = $this->locality('Tegucigalpa', 'tegucigalpa', true);
        DB::table('locations')->where('id', $locationId)->update(['locality_id' => $locality]);
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'City category offer', 'currency' => 'HNL', 'price_minor' => 1000, 'is_active' => true, 'is_visible' => true]);
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $query = app(DiscoveryQueryService::class);
        $projectionId = (string) DB::table('discovery_published_offers')->where('projection_key', 'product:'.$product->id)->value('id');
        $matching = $query->search(new DiscoveryQuery('offer', 'HN', null, 'HNL', null, null, 6, 'tegucigalpa', 'coffee'))->results;
        self::assertContains($projectionId, array_column($matching, 'projection_id'));
        $wrongCategory = $query->search(new DiscoveryQuery('offer', 'HN', null, 'HNL', null, null, 6, 'tegucigalpa', 'bakery'))->results;
        self::assertNotContains($projectionId, array_column($wrongCategory, 'projection_id'));
        self::assertNotSame($otherCategoryId, $categoryId);
    }

    public function test_projector_rerun_preserves_projection_identity_and_dimension_set(): void
    {
        [, $service] = $this->serviceWithLocations(['Tegucigalpa', 'San Pedro Sula'], true);
        app(PublishedCommercialProjectionService::class)->projectCurrent();
        $before = DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->sole();
        $beforeLocations = DB::table('discovery_offer_locations')->where('projection_id', $before->id)->orderBy('location_id')->pluck('location_id')->all();
        app(PublishedCommercialProjectionService::class)->projectCurrent();
        $after = DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->sole();
        $afterLocations = DB::table('discovery_offer_locations')->where('projection_id', $after->id)->orderBy('location_id')->pluck('location_id')->all();

        self::assertSame($before->id, $after->id);
        self::assertSame($beforeLocations, $afterLocations);
        self::assertSame(2, count($afterLocations));
    }

    public function test_removing_service_location_clears_stale_dimension_next_run(): void
    {
        [, $service] = $this->serviceWithLocations(['Tegucigalpa', 'San Pedro Sula'], true);
        app(PublishedCommercialProjectionService::class)->projectCurrent();
        $projectionId = (string) DB::table('discovery_published_offers')->where('projection_key', 'service:'.$service->id)->value('id');
        DB::table('service_locations')->where('service_id', $service->id)->orderBy('location_id')->limit(1)->delete();
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        self::assertSame(1, DB::table('discovery_offer_locations')->where('projection_id', $projectionId)->count());
    }

    public function test_raw_unpublished_canonical_records_are_not_searchable_without_projection(): void
    {
        $tenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $this->assignHonduras($tenant);
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'Not projected', 'currency' => 'HNL', 'price_minor' => 1000, 'is_active' => true, 'is_visible' => true]);

        $results = app(DiscoveryQueryService::class)->search(new DiscoveryQuery('Not projected', 'HN', null, 'HNL'))->results;
        self::assertSame([], $results);
    }

    public function test_evidence_identity_remains_stable_product_and_service_keys(): void
    {
        $tenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $locationId = $this->assignHonduras($tenant);
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'Evidence product', 'currency' => 'HNL', 'price_minor' => 1000, 'is_active' => true, 'is_visible' => true]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Evidence service', 'currency' => 'HNL', 'price_minor' => 1000, 'is_active' => true]);
        $this->attachServiceToLocation($tenant, $service->id, $locationId);
        $this->createAvailabilityRule($tenant, $locationId, $service->id, 'America/Tegucigalpa', '2026-08-05');
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        foreach ([['product', $product->id], ['service', $service->id]] as [$type, $id]) {
            $projectionId = DB::table('discovery_published_offers')->where('projection_key', $type.':'.$id)->value('id');
            self::assertSame([$type.':'.$id], DB::table('discovery_offer_evidence')->where('projection_id', $projectionId)->where('evidence_key', $type.':'.$id)->pluck('evidence_key')->all());
        }
    }

    public function test_fresh_sqlite_migration_exposes_expand_only_dimension_schema(): void
    {
        self::assertSame('sqlite', DB::getDriverName());
        self::assertTrue(Schema::hasTable('discovery_published_offers'));
        self::assertTrue(Schema::hasTable('discovery_offer_locations'));
    }

    private function category(string $slug, bool $active): int
    {
        return (int) DB::table('categories')->insertGetId([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'is_active' => $active,
            'is_featured' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function locality(string $name, string $slug, bool $active, string $countryCode = 'HN', string $timezoneName = 'America/Tegucigalpa'): int
    {
        return (int) DB::table('localities')->insertGetId([
            'country_code' => $countryCode,
            'administrative_area_id' => null,
            'name' => $name,
            'slug' => $slug,
            'timezone_name' => $timezoneName,
            'latitude' => null,
            'longitude' => null,
            'legacy_city_id' => null,
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{0:Tenant,1:Service,2:list<string>,3:list<string>} */
    private function serviceWithSameLocality(): array
    {
        $tenant = Tenant::factory()->create(['subscription_status' => 'active', 'verification_status' => 'verified']);
        $firstLocation = $this->assignHonduras($tenant, 'Primary city');
        $localityId = $this->locality('Same city', 'same-city', true);
        DB::table('locations')->where('id', $firstLocation)->update(['locality_id' => $localityId]);
        $secondLocation = $this->assignAdditionalLocation($tenant, 'Secondary city', $localityId);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Multi-city service',
            'description' => 'Service available in the same city.',
            'price' => 20.00,
            'price_minor' => 2000,
            'currency' => 'HNL',
            'is_active' => true,
        ]);
        foreach ([$firstLocation, $secondLocation] as $locationId) {
            $this->attachServiceToLocation($tenant, $service->id, $locationId);
            $this->createAvailabilityRule($tenant, $locationId, $service->id, 'America/Tegucigalpa', '2026-08-05');
        }

        return [$tenant, $service, ['same-city'], [$firstLocation, $secondLocation]];
    }

    /** @return array{0:Tenant,1:Service,2:list<string>} */
    private function serviceWithLocations(array $names, bool $allAvailable = true, bool $secondAvailable = true, int $availabilityIndex = 0): array
    {
        $tenant = Tenant::factory()->create(['subscription_status' => 'active', 'verification_status' => 'verified']);
        $firstLocation = $this->assignHonduras($tenant, $names[0]);
        $firstLocality = $this->locality($names[0], Str::slug($names[0]), true);
        DB::table('locations')->where('id', $firstLocation)->update(['locality_id' => $firstLocality]);
        $secondLocation = $this->assignAdditionalLocation($tenant, $names[1], $firstLocality);
        $secondLocality = $this->locality($names[1], Str::slug($names[1]), true);
        DB::table('locations')->where('id', $secondLocation)->update(['locality_id' => $secondLocality]);

        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Multi-city service',
            'description' => 'Service available in several cities.',
            'price' => 20.00,
            'price_minor' => 2000,
            'currency' => 'HNL',
            'is_active' => true,
        ]);
        $this->attachServiceToLocation($tenant, $service->id, $firstLocation);
        $this->attachServiceToLocation($tenant, $service->id, $secondLocation);
        if ($allAvailable || $availabilityIndex === 0) {
            $this->createAvailabilityRule($tenant, $firstLocation, $service->id, 'America/Tegucigalpa', '2026-08-05');
        }
        if ($allAvailable || ($secondAvailable && $availabilityIndex === 1)) {
            $this->createAvailabilityRule($tenant, $secondLocation, $service->id, 'America/Tegucigalpa', '2026-08-05');
        }

        return [$tenant, $service, [$this->slug($firstLocality), $this->slug($secondLocality)]];
    }

    private function assignAdditionalLocation(Tenant $tenant, string $name, int $localityId): string
    {
        $market = DB::table('tenant_markets')->where('tenant_id', $tenant->id)->first();
        $id = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $id,
            'tenant_id' => $tenant->id,
            'country_code' => 'HN',
            'administrative_area_id' => null,
            'locality_id' => $localityId,
            'locale_code' => $market->locale_code,
            'currency_code' => $market->currency_code,
            'timezone_name' => $market->timezone_name,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'address' => 'Address',
            'phone' => null,
            'contact_name' => null,
            'hours_data' => null,
            'is_active' => true,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function slug(int $localityId): string
    {
        return (string) DB::table('localities')->where('id', $localityId)->value('slug');
    }
}
