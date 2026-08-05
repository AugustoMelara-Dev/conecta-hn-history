<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Infrastructure\Discovery\CanonicalAvailabilityInspector;
use App\Infrastructure\Discovery\CanonicalPublishedOfferSource;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DiscoveryProjectionBatchingTest extends TestCase
{
    use BuildsDiscoveryFixtures;
    use RefreshDatabase;

    public function test_below_batch_catalog_growth_keeps_location_and_availability_query_counts_and_candidate_fields(): void
    {
        $tenant = Tenant::factory()->create([
            'subscription_status' => 'active',
            'verification_status' => 'verified',
        ]);
        $locationId = $this->assignHonduras($tenant);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Batch product 1',
            'description' => 'First batched product.',
            'price' => 10,
            'price_minor' => 1000,
            'currency' => 'HNL',
            'manage_stock' => false,
            'is_active' => true,
            'is_visible' => true,
        ]);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Batch service 1',
            'description' => 'First batched service.',
            'price' => 20,
            'price_minor' => 2000,
            'currency' => 'HNL',
            'is_active' => true,
        ]);
        $this->attachServiceToLocation($tenant, $service->id, $locationId);
        $this->createAvailabilityRule($tenant, $locationId, $service->id, 'America/Tegucigalpa', '2026-08-05');

        $counts = ['product' => 0, 'service' => 0, 'availability' => 0];
        DB::listen(function (QueryExecuted $query) use (&$counts): void {
            $sql = strtolower(str_replace(['"', '`'], '', $query->sql));
            if (str_contains($sql, 'from locations') && str_contains($sql, 'is_primary')) {
                $counts['product']++;
            }
            if (str_contains($sql, 'from service_locations')) {
                $counts['service']++;
            }
            if (str_contains($sql, 'from availability_rules')) {
                $counts['availability']++;
            }
        });

        $source = app(CanonicalPublishedOfferSource::class);
        $small = $source->currentOfferCandidates();
        $smallCounts = $counts;
        $smallByKey = collect($small)->keyBy('projection_key')->all();
        self::assertSame(['product' => 1, 'service' => 1, 'availability' => 1], $smallCounts);

        $counts = ['product' => 0, 'service' => 0, 'availability' => 0];
        for ($index = 2; $index <= 25; $index++) {
            Product::factory()->forTenant($tenant)->create([
                'name' => 'Batch product '.$index,
                'description' => 'Product '.$index,
                'price' => 10 + $index,
                'price_minor' => (10 + $index) * 100,
                'currency' => 'HNL',
                'manage_stock' => true,
                'stock_quantity' => $index,
                'is_active' => true,
                'is_visible' => true,
            ]);
            $extraService = Service::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Batch service '.$index,
                'description' => 'Service '.$index,
                'price' => 20 + $index,
                'price_minor' => (20 + $index) * 100,
                'currency' => 'HNL',
                'is_active' => true,
            ]);
            $this->attachServiceToLocation($tenant, $extraService->id, $locationId);
            $this->createAvailabilityRule($tenant, $locationId, $extraService->id, 'America/Tegucigalpa', '2026-08-05');
        }

        $large = $source->currentOfferCandidates();
        self::assertSame(['product' => 1, 'service' => 1, 'availability' => 1], $counts);
        self::assertCount(2, $small);
        self::assertCount(50, $large);
        $largeByKey = collect($large)->keyBy('projection_key')->all();
        self::assertSame(
            $this->canonicalize($smallByKey),
            $this->canonicalize(array_intersect_key($largeByKey, $smallByKey)),
        );
        self::assertSame(
            array_keys($largeByKey),
            collect($large)->pluck('projection_key')->all(),
            'Candidates remain grouped deterministically with products before services.',
        );
    }

    public function test_empty_source_does_not_issue_location_or_availability_queries(): void
    {
        $counts = ['product' => 0, 'service' => 0, 'availability' => 0];
        DB::listen(function (QueryExecuted $query) use (&$counts): void {
            $sql = strtolower(str_replace(['"', '`'], '', $query->sql));
            if (str_contains($sql, 'from locations') && str_contains($sql, 'is_primary')) {
                $counts['product']++;
            }
            if (str_contains($sql, 'from service_locations')) {
                $counts['service']++;
            }
            if (str_contains($sql, 'from availability_rules')) {
                $counts['availability']++;
            }
        });

        self::assertSame([], app(CanonicalPublishedOfferSource::class)->currentOfferCandidates());
        self::assertSame(['product' => 0, 'service' => 0, 'availability' => 0], $counts);
    }

    public function test_batch_availability_preserves_exact_scope_authority_and_scalar_behavior(): void
    {
        $tenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $locationId = $this->assignHonduras($tenant);
        $otherTenant = Tenant::factory()->create(['subscription_status' => 'active']);
        $otherLocationId = $this->assignHonduras($otherTenant, 'Other location');
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'currency' => 'HNL',
            'price_minor' => 1000,
            'is_active' => true,
        ]);
        $otherService = Service::factory()->create([
            'tenant_id' => $otherTenant->id,
            'currency' => 'HNL',
            'price_minor' => 1000,
            'is_active' => true,
        ]);
        $this->createAvailabilityRule($tenant, $locationId, $service->id, 'America/Tegucigalpa', '2026-08-05');
        $this->createAvailabilityRule($tenant, $locationId, $service->id, 'America/Tegucigalpa', '2026-08-06');
        DB::table('availability_rules')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'location_id' => $otherLocationId,
            'service_id' => $service->id,
            'service_provider_id' => null,
            'weekday' => 1,
            'starts_at_local' => '09:00',
            'ends_at_local' => '17:00',
            'timezone' => 'America/Tegucigalpa',
            'effective_from' => null,
            'effective_until' => null,
            'is_active' => true,
            'source' => 'canonical',
            'source_key' => 'cross-tenant-batch-rule',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inspector = app(CanonicalAvailabilityInspector::class);
        $scopes = [
            ['tenant_id' => (string) $tenant->id, 'service_id' => $service->id, 'location_id' => $locationId],
            ['tenant_id' => (string) $tenant->id, 'service_id' => $service->id, 'location_id' => $locationId],
            ['tenant_id' => (string) $tenant->id, 'service_id' => $otherService->id, 'location_id' => $otherLocationId],
            ['tenant_id' => (string) $otherTenant->id, 'service_id' => $service->id, 'location_id' => $locationId],
        ];
        $statuses = $inspector->projectableStatuses($scopes);

        self::assertSame('available', $statuses[(string) $tenant->id][$service->id][$locationId]);
        self::assertSame('unknown', $statuses[(string) $tenant->id][$otherService->id][$otherLocationId]);
        self::assertSame('unknown', $statuses[(string) $otherTenant->id][$service->id][$locationId]);
        self::assertSame(
            'available',
            $inspector->projectableStatus((string) $tenant->id, $locationId, $service->id),
        );
        self::assertSame('unknown', $inspector->projectableStatus((string) $tenant->id, null, $service->id));
    }

    public function test_product_location_batching_crosses_the_500_tenant_boundary(): void
    {
        $fixture = $this->seedProgram08ProductBoundaryFixture();
        $beforeHash = $this->program08StateHash();
        $counts = ['product' => 0, 'service' => 0, 'availability' => 0];
        DB::listen(function (QueryExecuted $query) use (&$counts): void {
            $sql = strtolower(str_replace(['"', '`'], '', $query->sql));
            if (str_contains($sql, 'from locations') && str_contains($sql, 'is_primary')) {
                $counts['product']++;
            }
            if (str_contains($sql, 'from service_locations')) {
                $counts['service']++;
            }
            if (str_contains($sql, 'from availability_rules')) {
                $counts['availability']++;
            }
        });

        $first = app(CanonicalPublishedOfferSource::class)->currentOfferCandidates();
        $firstCounts = $counts;
        $afterFirstHash = $this->program08StateHash();

        self::assertSame(['product' => 2, 'service' => 0, 'availability' => 0], $firstCounts);
        self::assertCount(501, $first);
        self::assertCount(501, collect($first)->pluck('projection_key')->unique());
        self::assertSame(['product'], array_values(array_unique(array_map(
            static fn (array $candidate): string => (string) $candidate['source_type'],
            $first,
        ))));

        $expectedProducts = DB::table('products')
            ->whereIn('tenant_id', $fixture['tenant_ids'])
            ->get(['tenant_id', 'id'])
            ->mapWithKeys(static fn (object $row): array => [(string) $row->tenant_id => (int) $row->id])
            ->all();
        ksort($expectedProducts, SORT_STRING);
        $actualProducts = collect($first)->mapWithKeys(static fn (array $candidate): array => [
            (string) $candidate['source_tenant_id'] => (int) $candidate['source_record_id'],
        ])->all();
        ksort($actualProducts, SORT_STRING);
        self::assertSame(
            $expectedProducts,
            $actualProducts,
        );

        $expectedLocations = DB::table('locations')
            ->whereIn('tenant_id', $fixture['tenant_ids'])
            ->pluck('id', 'tenant_id')
            ->map(static fn (mixed $locationId): string => (string) $locationId)
            ->all();
        $locationViolations = [];
        $unlistedTenants = [];
        foreach ($first as $candidate) {
            $tenantId = (string) $candidate['source_tenant_id'];
            if (! isset($fixture['tenant_set'][$tenantId])) {
                $unlistedTenants[] = $tenantId;
            }
            foreach ($candidate['locations'] as $location) {
                if ((string) ($location['source_tenant_id'] ?? '') !== $tenantId
                    || strtoupper((string) ($location['country_code'] ?? '')) !== 'HN'
                    || (string) ($location['location_id'] ?? '') !== ($expectedLocations[$tenantId] ?? null)) {
                    $locationViolations[] = [
                        'tenant_id' => $tenantId,
                        'location' => $location,
                    ];
                }
            }
        }
        self::assertSame([], $unlistedTenants);
        self::assertSame([], $locationViolations);
        $expectedTenantIds = $fixture['tenant_ids'];
        sort($expectedTenantIds, SORT_STRING);
        self::assertSame($expectedTenantIds, collect($first)->pluck('source_tenant_id')->map(static fn (mixed $id): string => (string) $id)->sort()->values()->all());

        $counts = ['product' => 0, 'service' => 0, 'availability' => 0];
        $second = app(CanonicalPublishedOfferSource::class)->currentOfferCandidates();
        $secondCounts = $counts;
        $afterSecondHash = $this->program08StateHash();

        self::assertSame(['product' => 2, 'service' => 0, 'availability' => 0], $secondCounts);
        self::assertSame(
            collect($first)->pluck('projection_key')->values()->all(),
            collect($second)->pluck('projection_key')->values()->all(),
        );
        self::assertSame($this->canonicalize($first), $this->canonicalize($second));
        self::assertSame($beforeHash, $afterFirstHash);
        self::assertSame($beforeHash, $afterSecondHash);
    }

    public function test_service_and_availability_batching_crosses_500_scopes_and_rejects_foreign_rows(): void
    {
        $fixture = $this->seedProgram08ServiceBoundaryFixture();
        $beforeHash = $this->program08StateHash();
        $beforeScopedState = $this->program08ScopedStateHashes(
            $fixture['subject_tenant_id'],
            $fixture['foreign_tenant_id'],
        );
        $counts = ['product' => 0, 'service' => 0, 'availability' => 0];
        DB::listen(function (QueryExecuted $query) use (&$counts): void {
            $sql = strtolower(str_replace(['"', '`'], '', $query->sql));
            if (str_contains($sql, 'from locations') && str_contains($sql, 'is_primary')) {
                $counts['product']++;
            }
            if (str_contains($sql, 'from service_locations')) {
                $counts['service']++;
            }
            if (str_contains($sql, 'from availability_rules')) {
                $counts['availability']++;
            }
        });

        $first = app(CanonicalPublishedOfferSource::class)->currentOfferCandidates();
        $firstCounts = $counts;
        $afterFirstHash = $this->program08StateHash();
        $afterFirstScopedState = $this->program08ScopedStateHashes(
            $fixture['subject_tenant_id'],
            $fixture['foreign_tenant_id'],
        );

        self::assertSame(['product' => 0, 'service' => 2, 'availability' => 2], $firstCounts);
        self::assertCount(501, $first);
        self::assertCount(501, collect($first)->pluck('projection_key')->unique());
        self::assertSame(['service'], array_values(array_unique(array_map(
            static fn (array $candidate): string => (string) $candidate['source_type'],
            $first,
        ))));

        $expectedServices = DB::table('services')
            ->where('tenant_id', $fixture['subject_tenant_id'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id'])
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $expectedLocations = DB::table('service_locations as sl')
            ->join('locations as l', 'l.id', '=', 'sl.location_id')
            ->where('sl.tenant_id', $fixture['subject_tenant_id'])
            ->where('l.tenant_id', $fixture['subject_tenant_id'])
            ->orderBy('sl.service_id')
            ->pluck('sl.location_id', 'sl.service_id')
            ->map(static fn (mixed $locationId): string => (string) $locationId)
            ->all();
        $candidateServiceIds = collect($first)->pluck('source_record_id')->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();
        self::assertSame($expectedServices, $candidateServiceIds);

        $ownershipViolations = [];
        $foreignLeaks = [];
        foreach ($first as $candidate) {
            $serviceId = (int) $candidate['source_record_id'];
            $tenantId = (string) $candidate['source_tenant_id'];
            if ($tenantId !== $fixture['subject_tenant_id'] || ! in_array($serviceId, $expectedServices, true)) {
                $ownershipViolations[] = $candidate;
            }
            foreach ($candidate['locations'] as $location) {
                if ((string) ($location['source_tenant_id'] ?? '') !== $fixture['subject_tenant_id']
                    || (string) ($location['location_id'] ?? '') !== ($expectedLocations[$serviceId] ?? null)
                    || strtoupper((string) ($location['country_code'] ?? '')) !== 'HN'
                    || (string) ($location['availability_status'] ?? '') !== 'available') {
                    $ownershipViolations[] = ['service' => $serviceId, 'location' => $location];
                }
                if (in_array((string) ($location['location_id'] ?? ''), $fixture['foreign_identifiers'], true)) {
                    $foreignLeaks[] = $location;
                }
            }
            foreach ($candidate['evidence'] as $evidence) {
                if (in_array((string) ($evidence['source_identifier'] ?? ''), $fixture['foreign_identifiers'], true)
                    || in_array((string) ($evidence['source_tenant_id'] ?? ''), $fixture['foreign_identifiers'], true)) {
                    $foreignLeaks[] = $evidence;
                }
            }
            $candidatePayload = json_encode(
                ['candidate' => $candidate, 'evidence' => $candidate['evidence']],
                JSON_THROW_ON_ERROR,
            );
            foreach ($fixture['foreign_rule_identifiers'] as $foreignIdentifier) {
                if (str_contains($candidatePayload, $foreignIdentifier)) {
                    $foreignLeaks[] = ['identifier' => $foreignIdentifier, 'candidate' => $candidate['projection_key']];
                }
            }
        }
        self::assertSame([], $ownershipViolations);
        self::assertSame([], $foreignLeaks);
        self::assertSame([], collect($first)->filter(static fn (array $candidate): bool => $candidate['locations'] === [])->all());
        self::assertSame(
            $fixture['subject_tenant_id'],
            collect($first)->pluck('source_tenant_id')->unique()->sole(),
        );

        $counts = ['product' => 0, 'service' => 0, 'availability' => 0];
        $second = app(CanonicalPublishedOfferSource::class)->currentOfferCandidates();
        $secondCounts = $counts;
        $afterSecondHash = $this->program08StateHash();
        $afterSecondScopedState = $this->program08ScopedStateHashes(
            $fixture['subject_tenant_id'],
            $fixture['foreign_tenant_id'],
        );

        self::assertSame(['product' => 0, 'service' => 2, 'availability' => 2], $secondCounts);
        self::assertSame(
            collect($first)->pluck('projection_key')->values()->all(),
            collect($second)->pluck('projection_key')->values()->all(),
        );
        self::assertSame($this->canonicalize($first), $this->canonicalize($second));
        self::assertSame($beforeHash, $afterFirstHash);
        self::assertSame($beforeHash, $afterSecondHash);
        self::assertSame($beforeScopedState['subject']['row_counts'], $afterFirstScopedState['subject']['row_counts']);
        self::assertSame($beforeScopedState['subject']['state_hash'], $afterFirstScopedState['subject']['state_hash']);
        self::assertSame($beforeScopedState['foreign']['row_counts'], $afterFirstScopedState['foreign']['row_counts']);
        self::assertSame($beforeScopedState['foreign']['state_hash'], $afterFirstScopedState['foreign']['state_hash']);
        self::assertSame($beforeScopedState['subject']['row_counts'], $afterSecondScopedState['subject']['row_counts']);
        self::assertSame($beforeScopedState['subject']['state_hash'], $afterSecondScopedState['subject']['state_hash']);
        self::assertSame($beforeScopedState['foreign']['row_counts'], $afterSecondScopedState['foreign']['row_counts']);
        self::assertSame($beforeScopedState['foreign']['state_hash'], $afterSecondScopedState['foreign']['state_hash']);
    }

    /** @return array{tenant_ids:list<string>,tenant_set:array<string,bool>} */
    private function seedProgram08ProductBoundaryFixture(): array
    {
        $now = now()->format('Y-m-d H:i:s');
        $marketPackVersionId = $this->seedProgram08HondurasMarket($now, 'products');
        $tenantIds = [];
        $tenants = [];
        $markets = [];
        $locations = [];
        for ($index = 1; $index <= 501; $index++) {
            $tenantId = $this->program08Uuid('product-tenant-'.$index);
            $locationId = $this->program08Uuid('product-location-'.$index);
            $tenantIds[] = $tenantId;
            $tenants[] = [
                'id' => $tenantId,
                'user_id' => null,
                'name' => 'Program08 Product Tenant '.$index,
                'slug' => 'program08-product-tenant-'.$index,
                'plan' => 'SEED',
                'plan_type' => 'semilla',
                'is_active' => true,
                'subscription_status' => 'active',
                'status' => 'approved',
                'verification_status' => 'verified',
                'verified_at' => $now,
                'verification_reviewed_at' => $now,
                'ai_balance' => 0,
                'ai_reserved_balance' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $markets[] = [
                'id' => $this->program08Uuid('product-market-'.$index),
                'tenant_id' => $tenantId,
                'market_pack_version_id' => $marketPackVersionId,
                'country_code' => 'HN',
                'locale_code' => 'es-HN',
                'currency_code' => 'HNL',
                'timezone_name' => 'America/Tegucigalpa',
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $locations[] = [
                'id' => $locationId,
                'tenant_id' => $tenantId,
                'country_code' => 'HN',
                'administrative_area_id' => null,
                'locality_id' => null,
                'locale_code' => 'es-HN',
                'currency_code' => 'HNL',
                'timezone_name' => 'America/Tegucigalpa',
                'name' => 'Program08 Product Location '.$index,
                'slug' => 'program08-product-location-'.$index,
                'address' => 'Program08 product address '.$index,
                'phone' => null,
                'contact_name' => null,
                'hours_data' => null,
                'is_active' => true,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->program08InsertChunks('tenants', $tenants);
        $this->program08InsertChunks('tenant_markets', $markets);
        $this->program08InsertChunks('locations', $locations);

        $products = [];
        foreach ($tenantIds as $index => $tenantId) {
            $number = $index + 1;
            $priceMinor = 1000 + $number;
            $products[] = [
                'tenant_id' => $tenantId,
                'name' => 'Program08 Product '.$number,
                'slug' => 'program08-product-'.$number,
                'description' => 'Program08 deterministic product '.$number,
                'price' => number_format($priceMinor / 100, 2, '.', ''),
                'currency' => 'HNL',
                'price_minor' => $priceMinor,
                'image_path' => null,
                'gallery' => null,
                'blur_hash' => null,
                'is_active' => true,
                'is_featured' => false,
                'is_visible' => true,
                'is_locked' => false,
                'is_new' => false,
                'is_impulse_buy' => false,
                'manage_stock' => false,
                'stock_quantity' => 0,
                'low_stock_threshold' => 0,
                'allow_backorders' => false,
                'stock_status' => 'in_stock',
                'variants' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->program08InsertChunks('products', $products);

        return [
            'tenant_ids' => $tenantIds,
            'tenant_set' => array_fill_keys($tenantIds, true),
        ];
    }

    /** @return array{subject_tenant_id:string,foreign_tenant_id:string,foreign_identifiers:list<string>,foreign_rule_identifiers:list<string>,mismatch_service_location_outcome:string,mismatch_availability_rule_outcome:string} */
    private function seedProgram08ServiceBoundaryFixture(): array
    {
        $now = now()->format('Y-m-d H:i:s');
        $marketPackVersionId = $this->seedProgram08HondurasMarket($now, 'services');
        $subjectTenantId = $this->program08Uuid('service-subject-tenant');
        $foreignTenantId = $this->program08Uuid('service-foreign-tenant');
        $subjectLocationIds = [];
        $tenants = [
            [
                'id' => $subjectTenantId,
                'user_id' => null,
                'name' => 'Program08 Service Subject',
                'slug' => 'program08-service-subject',
                'plan' => 'SEED',
                'plan_type' => 'semilla',
                'is_active' => true,
                'subscription_status' => 'active',
                'status' => 'approved',
                'verification_status' => 'verified',
                'verified_at' => $now,
                'verification_reviewed_at' => $now,
                'ai_balance' => 0,
                'ai_reserved_balance' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $foreignTenantId,
                'user_id' => null,
                'name' => 'Program08 Service Foreign',
                'slug' => 'program08-service-foreign',
                'plan' => 'SEED',
                'plan_type' => 'semilla',
                'is_active' => true,
                'subscription_status' => 'active',
                'status' => 'approved',
                'verification_status' => 'verified',
                'verified_at' => $now,
                'verification_reviewed_at' => $now,
                'ai_balance' => 0,
                'ai_reserved_balance' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        $markets = [[
            'id' => $this->program08Uuid('service-subject-market'),
            'tenant_id' => $subjectTenantId,
            'market_pack_version_id' => $marketPackVersionId,
            'country_code' => 'HN',
            'locale_code' => 'es-HN',
            'currency_code' => 'HNL',
            'timezone_name' => 'America/Tegucigalpa',
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]];
        $locations = [];
        for ($index = 1; $index <= 501; $index++) {
            $locationId = $this->program08Uuid('service-subject-location-'.$index);
            $subjectLocationIds[$index] = $locationId;
            $locations[] = [
                'id' => $locationId,
                'tenant_id' => $subjectTenantId,
                'country_code' => 'HN',
                'administrative_area_id' => null,
                'locality_id' => null,
                'locale_code' => 'es-HN',
                'currency_code' => 'HNL',
                'timezone_name' => 'America/Tegucigalpa',
                'name' => 'Program08 Service Location '.$index,
                'slug' => 'program08-service-location-'.$index,
                'address' => 'Program08 service address '.$index,
                'phone' => null,
                'contact_name' => null,
                'hours_data' => null,
                'is_active' => true,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $foreignLocationId = $this->program08Uuid('service-foreign-location');
        $locations[] = [
            'id' => $foreignLocationId,
            'tenant_id' => $foreignTenantId,
            'country_code' => 'HN',
            'administrative_area_id' => null,
            'locality_id' => null,
            'locale_code' => 'es-HN',
            'currency_code' => 'HNL',
            'timezone_name' => 'America/Tegucigalpa',
            'name' => 'Program08 Foreign Location',
            'slug' => 'program08-foreign-location',
            'address' => 'Program08 foreign address',
            'phone' => null,
            'contact_name' => null,
            'hours_data' => null,
            'is_active' => true,
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->program08InsertChunks('tenants', $tenants);
        $this->program08InsertChunks('tenant_markets', $markets);
        $this->program08InsertChunks('locations', $locations);

        $services = [];
        for ($index = 1; $index <= 501; $index++) {
            $priceMinor = 2000 + $index;
            $services[] = [
                'tenant_id' => $subjectTenantId,
                'name' => 'Program08 Service '.$index,
                'description' => 'Program08 deterministic service '.$index,
                'duration_minutes' => 30,
                'price' => number_format($priceMinor / 100, 2, '.', ''),
                'currency' => 'HNL',
                'price_minor' => $priceMinor,
                'write_idempotency_key' => null,
                'write_payload_hash' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->program08InsertChunks('services', $services);
        $subjectServiceIds = DB::table('services')
            ->where('tenant_id', $subjectTenantId)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $foreignService = [
            'tenant_id' => $foreignTenantId,
            'name' => 'Program08 Foreign Service',
            'description' => 'Program08 foreign sentinel service',
            'duration_minutes' => 30,
            'price' => '99.99',
            'currency' => 'HNL',
            'price_minor' => 9999,
            'write_idempotency_key' => null,
            'write_payload_hash' => null,
            'is_active' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        DB::table('services')->insert($foreignService);
        $foreignServiceId = (int) DB::table('services')->where('tenant_id', $foreignTenantId)->value('id');

        $serviceLocations = [];
        $availabilityRules = [];
        foreach ($subjectServiceIds as $index => $serviceId) {
            $number = $index + 1;
            $locationId = $subjectLocationIds[$number];
            $serviceLocations[] = [
                'tenant_id' => $subjectTenantId,
                'service_id' => $serviceId,
                'location_id' => $locationId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $availabilityRules[] = [
                'id' => $this->program08Uuid('service-availability-'.$number),
                'tenant_id' => $subjectTenantId,
                'location_id' => $locationId,
                'service_id' => $serviceId,
                'service_provider_id' => null,
                'weekday' => 3,
                'starts_at_local' => '09:00',
                'ends_at_local' => '17:00',
                'timezone' => 'America/Tegucigalpa',
                'effective_from' => null,
                'effective_until' => null,
                'is_active' => true,
                'source' => 'canonical',
                'source_key' => 'program08-service-availability-'.$number,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $serviceLocations[] = [
            'tenant_id' => $foreignTenantId,
            'service_id' => $foreignServiceId,
            'location_id' => $foreignLocationId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $foreignAvailabilityRuleId = $this->program08Uuid('service-foreign-availability');
        $availabilityRules[] = [
            'id' => $foreignAvailabilityRuleId,
            'tenant_id' => $foreignTenantId,
            'location_id' => $foreignLocationId,
            'service_id' => $foreignServiceId,
            'service_provider_id' => null,
            'weekday' => 3,
            'starts_at_local' => '09:00',
            'ends_at_local' => '17:00',
            'timezone' => 'America/Tegucigalpa',
            'effective_from' => null,
            'effective_until' => null,
            'is_active' => true,
            'source' => 'canonical',
            'source_key' => 'program08-foreign-availability',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->program08InsertChunks('service_locations', $serviceLocations);
        $this->program08InsertChunks('availability_rules', $availabilityRules);

        $mismatchServiceLocation = [
            'tenant_id' => $subjectTenantId,
            'service_id' => $subjectServiceIds[0],
            'location_id' => $foreignLocationId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $mismatchServiceLocationAccepted = false;
        $mismatchServiceLocationRejected = false;
        $mismatchServiceLocationError = null;
        try {
            DB::table('service_locations')->insert($mismatchServiceLocation);
            $mismatchServiceLocationAccepted = true;
        } catch (\Throwable $exception) {
            $mismatchServiceLocationRejected = true;
            $mismatchServiceLocationError = trim($exception->getMessage());
        }
        self::assertTrue(
            $mismatchServiceLocationAccepted xor $mismatchServiceLocationRejected,
            'Mismatched service_location must have exactly one outcome.',
        );
        if ($mismatchServiceLocationAccepted) {
            self::assertTrue(
                DB::table('service_locations')->where($mismatchServiceLocation)->exists(),
                'Accepted mismatched service_location must be directly query-visible.',
            );
        } else {
            self::assertNotSame('', $mismatchServiceLocationError, 'Rejected service_location must expose a constraint error.');
        }

        $mismatchAvailabilityRuleId = $this->program08Uuid('service-mismatched-availability');
        $mismatchAvailabilityRule = [
            'id' => $mismatchAvailabilityRuleId,
            'tenant_id' => $subjectTenantId,
            'location_id' => $foreignLocationId,
            'service_id' => $subjectServiceIds[0],
            'service_provider_id' => null,
            'weekday' => 3,
            'starts_at_local' => '09:00',
            'ends_at_local' => '17:00',
            'timezone' => 'America/Tegucigalpa',
            'effective_from' => null,
            'effective_until' => null,
            'is_active' => true,
            'source' => 'canonical',
            'source_key' => 'program08-mismatched-availability',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $mismatchAvailabilityRuleAccepted = false;
        $mismatchAvailabilityRuleRejected = false;
        $mismatchAvailabilityRuleError = null;
        try {
            DB::table('availability_rules')->insert($mismatchAvailabilityRule);
            $mismatchAvailabilityRuleAccepted = true;
        } catch (\Throwable $exception) {
            $mismatchAvailabilityRuleRejected = true;
            $mismatchAvailabilityRuleError = trim($exception->getMessage());
        }
        self::assertTrue(
            $mismatchAvailabilityRuleAccepted xor $mismatchAvailabilityRuleRejected,
            'Mismatched availability rule must have exactly one outcome.',
        );
        if ($mismatchAvailabilityRuleAccepted) {
            self::assertTrue(
                DB::table('availability_rules')->where('id', $mismatchAvailabilityRuleId)->exists(),
                'Accepted mismatched availability rule must be directly query-visible.',
            );
        } else {
            self::assertNotSame('', $mismatchAvailabilityRuleError, 'Rejected availability rule must expose a constraint error.');
        }

        return [
            'subject_tenant_id' => $subjectTenantId,
            'foreign_tenant_id' => $foreignTenantId,
            'foreign_identifiers' => [
                $foreignTenantId,
                $foreignLocationId,
                (string) $foreignServiceId,
                $foreignAvailabilityRuleId,
                $mismatchAvailabilityRuleId,
            ],
            'foreign_rule_identifiers' => [$foreignAvailabilityRuleId, $mismatchAvailabilityRuleId],
            'mismatch_service_location_outcome' => $mismatchServiceLocationAccepted ? 'accepted' : 'rejected',
            'mismatch_availability_rule_outcome' => $mismatchAvailabilityRuleAccepted ? 'accepted' : 'rejected',
        ];
    }

    private function seedProgram08HondurasMarket(string $now, string $scope): string
    {
        DB::table('countries')->updateOrInsert(
            ['code' => 'HN'],
            [
                'name' => 'Honduras',
                'native_name' => 'Honduras',
                'calling_code' => '+504',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('currencies')->updateOrInsert(
            ['code' => 'HNL'],
            [
                'name' => 'Lempira',
                'symbol' => 'L',
                'minor_unit' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('locales')->updateOrInsert(
            ['code' => 'es-HN'],
            [
                'name' => 'Spanish (Honduras)',
                'native_name' => 'Español (Honduras)',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('timezones')->updateOrInsert(
            ['name' => 'America/Tegucigalpa'],
            [
                'label' => 'Central America — Tegucigalpa',
                'utc_offset' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $marketPackId = $this->program08Uuid('market-pack-hn');
        $marketPackVersionId = $this->program08Uuid('market-pack-version-hn');
        DB::table('market_packs')->updateOrInsert(
            ['key' => 'hn'],
            [
                'id' => $marketPackId,
                'country_code' => 'HN',
                'name' => 'Honduras',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('market_pack_versions')->updateOrInsert(
            ['market_pack_id' => $marketPackId, 'version' => 1],
            [
                'id' => $marketPackVersionId,
                'status' => 'published',
                'default_locale_code' => 'es-HN',
                'default_currency_code' => 'HNL',
                'default_timezone_name' => 'America/Tegucigalpa',
                'supported_locales' => json_encode(['es-HN'], JSON_THROW_ON_ERROR),
                'supported_currencies' => json_encode(['HNL'], JSON_THROW_ON_ERROR),
                'supported_timezones' => json_encode(['America/Tegucigalpa'], JSON_THROW_ON_ERROR),
                'capabilities' => json_encode([], JSON_THROW_ON_ERROR),
                'address_schema' => json_encode(['levels' => ['department', 'city']], JSON_THROW_ON_ERROR),
                'effective_from' => $now,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        return $marketPackVersionId;
    }

    /** @param list<array<string,mixed>> $rows */
    private function program08InsertChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function program08Uuid(string $key): string
    {
        $hex = substr(hash('sha256', 'dovra-program08-'.$key), 0, 32);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }

    private function program08StateHash(): string
    {
        $tables = [
            'tenants',
            'tenant_markets',
            'locations',
            'services',
            'service_locations',
            'availability_rules',
            'products',
            'discovery_published_offers',
            'discovery_offer_locations',
            'discovery_offer_dimensions',
            'discovery_offer_evidence',
            'discovery_marketplace_integrity_records',
            'discovery_buyer_action_preparations',
            'ai_providers',
            'ai_provider_versions',
            'ai_models',
            'ai_model_versions',
            'ai_agent_definitions',
            'ai_agent_versions',
            'ai_release_policies',
            'ai_agent_activations',
        ];
        $state = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $rows = DB::table($table)->get()->map(static fn (object $row): array => get_object_vars($row))->all();
            usort($rows, static fn (array $left, array $right): int => strcmp(
                json_encode($left, JSON_THROW_ON_ERROR),
                json_encode($right, JSON_THROW_ON_ERROR),
            ));
            $state[$table] = $rows;
        }

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{
     *     subject:array{row_counts:array<string,int>,state_hash:string},
     *     foreign:array{row_counts:array<string,int>,state_hash:string}
     * }
     */
    private function program08ScopedStateHashes(string $subjectTenantId, string $foreignTenantId): array
    {
        $tables = [
            'tenants',
            'tenant_markets',
            'locations',
            'services',
            'service_locations',
            'availability_rules',
            'products',
            'discovery_published_offers',
            'discovery_offer_locations',
            'discovery_offer_evidence',
            'discovery_marketplace_integrity_records',
            'discovery_buyer_action_preparations',
            'ai_providers',
            'ai_provider_versions',
            'ai_models',
            'ai_model_versions',
            'ai_agent_definitions',
            'ai_agent_versions',
            'ai_prompt_definitions',
            'ai_prompt_versions',
            'ai_release_policies',
            'ai_agent_activations',
        ];
        $projectionRelationTables = [
            'discovery_marketplace_integrity_records',
        ];
        $snapshots = [];
        foreach (['subject' => $subjectTenantId, 'foreign' => $foreignTenantId] as $scope => $tenantId) {
            $projectionIds = Schema::hasTable('discovery_published_offers')
                ? DB::table('discovery_published_offers')
                    ->where('source_tenant_id', $tenantId)
                    ->pluck('id')
                    ->all()
                : [];
            $rowsByTable = [];
            $rowCounts = [];
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $query = DB::table($table);
                if ($table === 'tenants') {
                    $query->where('id', $tenantId);
                } elseif (Schema::hasColumn($table, 'tenant_id')) {
                    $query->where('tenant_id', $tenantId);
                } elseif (Schema::hasColumn($table, 'source_tenant_id')) {
                    $query->where('source_tenant_id', $tenantId);
                } elseif (in_array($table, $projectionRelationTables, true)) {
                    if ($projectionIds === []) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->whereIn('projection_id', $projectionIds);
                    }
                }

                $rows = $query->get()->map(static fn (object $row): array => get_object_vars($row))->all();
                usort($rows, static fn (array $left, array $right): int => strcmp(
                    json_encode($left, JSON_THROW_ON_ERROR),
                    json_encode($right, JSON_THROW_ON_ERROR),
                ));
                $rowCounts[$table] = count($rows);
                $rowsByTable[$table] = $rows;
            }

            $snapshots[$scope] = [
                'row_counts' => $rowCounts,
                'state_hash' => hash('sha256', json_encode($rowsByTable, JSON_THROW_ON_ERROR)),
            ];
        }

        return $snapshots;
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        return $value;
    }
}
