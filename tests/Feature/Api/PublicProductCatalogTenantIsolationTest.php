<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicProductCatalogTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_accepts_uuid_and_only_lists_products_from_that_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'Producto local']);
        Product::factory()->forTenant($otherTenant)->create(['name' => 'Producto ajeno']);

        $this->getJson('/api/v1/products?tenant_id='.$tenant->getKey())
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->getKey())
            ->assertJsonMissing(['name' => 'Producto ajeno']);
    }

    public function test_product_detail_hides_an_id_owned_by_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $otherProduct = Product::factory()->forTenant($otherTenant)->create();

        $this->getJson('/api/v1/products/'.$otherProduct->getKey().'?tenant_id='.$tenant->getKey())
            ->assertNotFound();
    }
}
