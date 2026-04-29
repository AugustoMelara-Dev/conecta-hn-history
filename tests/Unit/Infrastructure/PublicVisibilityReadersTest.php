<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Catalog\EloquentPublicCatalogReader;
use App\Infrastructure\Search\EloquentGlobalSearchReader;
use App\Infrastructure\Search\EloquentSearchRepository;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PublicVisibilityReadersTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantContext = app(TenantContext::class);
        $tenantContext->reset();
        $tenantContext->setAsSystemRoot(true);
    }

    public function test_public_catalog_reader_only_returns_products_from_published_tenants(): void
    {
        $reader = new EloquentPublicCatalogReader;

        $publishedTenant = Tenant::factory()->create([
            'slug' => 'visible-tenant',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => null,
        ]);

        $provisionalTenant = Tenant::factory()->create([
            'slug' => 'provisional-tenant',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
            'publication_paused_at' => null,
        ]);

        Product::factory()->forTenant($publishedTenant)->create([
            'name' => 'Visible Product',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Product::factory()->forTenant($provisionalTenant)->create([
            'name' => 'Hidden Product',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $featured = $reader->getFeaturedProducts(10);
        $featuredSlugs = collect($featured)->pluck('tenant.slug')->filter()->values()->all();

        $this->assertContains('visible-tenant', $featuredSlugs);
        $this->assertNotContains('provisional-tenant', $featuredSlugs);
    }

    public function test_public_catalog_reader_locations_and_directory_only_include_published_tenants(): void
    {
        $reader = new EloquentPublicCatalogReader;

        Tenant::factory()->create([
            'slug' => 'location-visible',
            'name' => 'Location Visible',
            'latitude' => 14.0723,
            'longitude' => -87.1921,
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => null,
        ]);

        Tenant::factory()->create([
            'slug' => 'location-paused',
            'name' => 'Location Paused',
            'latitude' => 14.0723,
            'longitude' => -87.1921,
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => now(),
        ]);

        $locations = $reader->getLocations();
        $directory = $reader->getDirectoryListing();

        $locationSlugs = collect($locations)->pluck('slug')->filter()->values()->all();
        $directorySlugs = collect($directory)->pluck('slug')->filter()->values()->all();

        $this->assertContains('location-visible', $locationSlugs);
        $this->assertNotContains('location-paused', $locationSlugs);
        $this->assertContains('location-visible', $directorySlugs);
        $this->assertNotContains('location-paused', $directorySlugs);
    }

    public function test_global_search_reader_only_returns_published_tenants(): void
    {
        $reader = new EloquentGlobalSearchReader;

        Tenant::factory()->create([
            'slug' => 'cafe-visible',
            'name' => 'Cafe Visible',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => null,
        ]);

        Tenant::factory()->create([
            'slug' => 'cafe-rejected',
            'name' => 'Cafe Rejected',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_REJECTED,
            'publication_paused_at' => null,
        ]);

        $results = $reader->searchTenants('Cafe', null, null, 20);
        $slugs = collect($results)->pluck('slug')->filter()->values()->all();

        $this->assertContains('cafe-visible', $slugs);
        $this->assertNotContains('cafe-rejected', $slugs);
    }

    public function test_search_repository_only_returns_published_tenants(): void
    {
        $repository = new EloquentSearchRepository;

        Tenant::factory()->create([
            'slug' => 'search-visible',
            'name' => 'Search Visible',
            'description' => 'Tienda oficial visible',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_VERIFIED,
            'publication_paused_at' => null,
        ]);

        Tenant::factory()->create([
            'slug' => 'search-provisional',
            'name' => 'Search Provisional',
            'description' => 'Tienda provisional',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => Tenant::VERIFICATION_STATUS_PROVISIONAL,
            'publication_paused_at' => null,
        ]);

        $results = $repository->searchTenants('Search', null, null, 20);
        $slugs = collect($results)->pluck('slug')->filter()->values()->all();

        $this->assertContains('search-visible', $slugs);
        $this->assertNotContains('search-provisional', $slugs);
    }
}
