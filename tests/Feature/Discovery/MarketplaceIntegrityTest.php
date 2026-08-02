<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Application\Discovery\DiscoveryQueryService;
use App\Application\Discovery\MarketplaceIntegrityService;
use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Domain\Discovery\DiscoveryQuery;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MarketplaceIntegrityTest extends TestCase
{
    use BuildsDiscoveryFixtures;
    use RefreshDatabase;

    public function test_it_records_reports_and_appeals_and_removes_flagged_offers_from_search(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Cafe Centro',
            'slug' => 'cafe-centro',
            'subscription_status' => 'active',
            'verification_status' => 'verified',
        ]);
        $this->assignHonduras($tenant);

        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Café semanal',
            'description' => 'Publicado y elegible.',
            'price' => 80.00,
            'price_minor' => 8000,
            'currency' => 'HNL',
            'is_active' => true,
            'is_visible' => true,
        ]);

        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $projectionId = (string) DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$product->id)
            ->value('id');

        $reportId = app(MarketplaceIntegrityService::class)->reportProjection(
            projectionId: $projectionId,
            reporterFingerprint: 'browser:hn-buyer-001',
            reason: 'possible prohibited offer',
        );
        app(MarketplaceIntegrityService::class)->moderateProjection(
            projectionId: $projectionId,
            status: 'rejected',
            reason: 'prohibited_offer',
        );
        app(MarketplaceIntegrityService::class)->appealReport(
            projectionId: $projectionId,
            reportId: $reportId,
            sellerReference: 'seller:cafe-centro',
            reason: 'appeal submitted with corrected evidence',
        );

        $results = app(DiscoveryQueryService::class)->search(new DiscoveryQuery(
            searchText: 'cafe semanal',
            marketCountryCode: 'HN',
            budgetMinor: 10000,
            currencyCode: 'HNL',
            desiredLocalDate: null,
            offerType: 'product',
            limit: 5,
        ));

        self::assertSame([], $results->results);
        self::assertDatabaseHas('discovery_marketplace_integrity_records', [
            'projection_id' => $projectionId,
            'record_type' => 'report',
            'status' => 'open',
        ]);
        self::assertDatabaseHas('discovery_marketplace_integrity_records', [
            'projection_id' => $projectionId,
            'record_type' => 'moderation',
            'status' => 'rejected',
        ]);
        self::assertDatabaseHas('discovery_marketplace_integrity_records', [
            'projection_id' => $projectionId,
            'record_type' => 'appeal',
            'status' => 'submitted',
        ]);
    }
}
