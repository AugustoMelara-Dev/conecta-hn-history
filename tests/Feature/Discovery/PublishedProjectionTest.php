<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PublishedProjectionTest extends TestCase
{
    use BuildsDiscoveryFixtures;
    use RefreshDatabase;

    public function test_it_builds_an_idempotent_public_projection_with_integrity_and_evidence_records(): void
    {
        $hnTenant = Tenant::factory()->create([
            'name' => 'Cafe Centro',
            'slug' => 'cafe-centro',
            'subscription_status' => 'active',
            'verification_status' => 'verified',
            'plan_type' => 'enterprise',
        ]);
        $hnLocationId = $this->assignHonduras($hnTenant);

        $coffee = Product::factory()->forTenant($hnTenant)->create([
            'name' => 'Café filtrado',
            'description' => 'Tueste diario para oficina.',
            'price' => 85.00,
            'price_minor' => 8500,
            'currency' => 'HNL',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $staleCoffee = Product::factory()->forTenant($hnTenant)->create([
            'name' => 'Café desactualizado',
            'description' => 'Oferta vieja.',
            'price' => 70.00,
            'price_minor' => 7000,
            'currency' => 'HNL',
            'is_active' => true,
            'is_visible' => true,
            'updated_at' => now()->subDays(45),
        ]);

        $hidden = Product::factory()->forTenant($hnTenant)->create([
            'name' => 'Inventario privado',
            'description' => 'No debe publicarse.',
            'price' => 60.00,
            'price_minor' => 6000,
            'currency' => 'HNL',
            'is_active' => true,
            'is_visible' => false,
        ]);

        $prohibited = Product::factory()->forTenant($hnTenant)->create([
            'name' => 'Lote prohibido para exportación',
            'description' => 'Prohibited sample only.',
            'price' => 100.00,
            'price_minor' => 10000,
            'currency' => 'HNL',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $caTenant = Tenant::factory()->create([
            'name' => 'Studio North',
            'slug' => 'studio-north',
            'subscription_status' => 'trial',
            'verification_status' => 'verified',
            'plan_type' => 'semilla',
        ]);
        $caLocationId = $this->assignCanada($caTenant);

        $service = Service::factory()->create([
            'tenant_id' => $caTenant->id,
            'name' => 'Portrait Session',
            'description' => 'Published portrait service.',
            'price' => 120.00,
            'price_minor' => 12000,
            'currency' => 'CAD',
            'is_active' => true,
        ]);
        $this->attachServiceToLocation($caTenant, $service->id, $caLocationId);
        $this->createAvailabilityRule(
            tenant: $caTenant,
            locationId: $caLocationId,
            serviceId: $service->id,
            timezoneName: 'America/Toronto',
            localDate: '2026-08-05',
        );

        $serviceWithoutAvailability = Service::factory()->create([
            'tenant_id' => $caTenant->id,
            'name' => 'Ghost Session',
            'description' => 'No availability should make this ineligible.',
            'price' => 90.00,
            'price_minor' => 9000,
            'currency' => 'CAD',
            'is_active' => true,
        ]);
        $this->attachServiceToLocation($caTenant, $serviceWithoutAvailability->id, $caLocationId);

        app(PublishedCommercialProjectionService::class)->projectCurrent();
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $coffeeProjection = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$coffee->id)
            ->sole();

        self::assertSame('HN', $coffeeProjection->country_code);
        self::assertSame('HNL', $coffeeProjection->currency_code);
        self::assertTrue((bool) $coffeeProjection->is_published);
        self::assertTrue((bool) $coffeeProjection->is_current);
        self::assertTrue((bool) $coffeeProjection->is_eligible);
        self::assertSame('verified', $coffeeProjection->verification_level);
        self::assertSame('approved', $coffeeProjection->moderation_status);

        $caProjection = DB::table('discovery_published_offers')
            ->where('projection_key', 'service:'.$service->id)
            ->sole();

        self::assertSame('CA', $caProjection->country_code);
        self::assertSame('CAD', $caProjection->currency_code);
        self::assertSame('America/Toronto', $caProjection->timezone_name);
        self::assertSame('available', $caProjection->availability_status);
        self::assertTrue((bool) $caProjection->is_eligible);

        $staleProjection = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$staleCoffee->id)
            ->sole();
        self::assertFalse((bool) $staleProjection->is_current);
        self::assertFalse((bool) $staleProjection->is_eligible);

        $prohibitedProjection = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$prohibited->id)
            ->sole();
        self::assertTrue((bool) $prohibitedProjection->prohibited_offer);
        self::assertFalse((bool) $prohibitedProjection->is_eligible);
        self::assertSame('rejected', $prohibitedProjection->moderation_status);

        $ghostProjection = DB::table('discovery_published_offers')
            ->where('projection_key', 'service:'.$serviceWithoutAvailability->id)
            ->sole();
        self::assertSame('unknown', $ghostProjection->availability_status);
        self::assertFalse((bool) $ghostProjection->is_eligible);

        $this->assertDatabaseMissing('discovery_published_offers', [
            'projection_key' => 'product:'.$hidden->id,
        ]);

        self::assertGreaterThanOrEqual(2, DB::table('discovery_offer_evidence')
            ->where('projection_id', $coffeeProjection->id)
            ->count());
        self::assertGreaterThanOrEqual(3, DB::table('discovery_offer_evidence')
            ->where('projection_id', $caProjection->id)
            ->count());

        self::assertSame(
            ['completeness', 'freshness', 'moderation', 'verification'],
            DB::table('discovery_marketplace_integrity_records')
                ->where('projection_id', $coffeeProjection->id)
                ->orderBy('record_type')
                ->pluck('record_type')
                ->all(),
        );

        self::assertSame(
            5,
            DB::table('discovery_published_offers')->count(),
            'Upsert should keep one projection row per canonical offer across repeated runs.',
        );
    }
}
