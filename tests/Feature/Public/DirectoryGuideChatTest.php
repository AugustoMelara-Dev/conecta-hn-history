<?php

declare(strict_types=1);

use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\GlobalMarketFixture;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('answers public directory guide questions with only public catalog data', function () {
    $platform = Tenant::factory()->create();
    GlobalMarketFixture::assignCanada($platform);
    config()->set('ai.public.billing_tenant_id', (string) $platform->id);

    $tenant = Tenant::factory()->create([
        'name' => 'Comedor La Ceiba',
        'slug' => 'comedor-la-ceiba',
        'is_active' => true,
        'subscription_status' => 'active',
    ]);
    GlobalMarketFixture::assignCanada($tenant);

    $product = Product::factory()->forTenant($tenant)->make([
        'name' => 'Baleada con cafe',
        'description' => 'Comida tipica economica',
        'price' => 95,
        'price_minor' => 9500,
        'currency' => 'CAD',
        'is_visible' => true,
        'status' => 'active',
    ]);
    $attributes = collect($product->getAttributes())
        ->filter(fn ($value, string $key): bool => Schema::hasColumn('products', $key))
        ->all();
    $persistedProduct = new Product;
    $persistedProduct->forceFill($attributes);
    $persistedProduct->save();

    app(PublishedCommercialProjectionService::class)->projectCurrent();

    $response = postJson('/api/v1/directory/guide/chat', [
        'message' => 'Que puedo comprar de comida con 150 lempiras?',
        'budget' => 150,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('meta.llm_used', false)
        ->assertJsonPath('meta.privacy_scope', 'public_directory_only')
        ->assertJsonFragment([
            'name' => 'Baleada con cafe',
            'business_name' => 'Comedor La Ceiba',
            'price_label' => 'CAD 95.00',
        ]);
});

it('returns an honest fallback instead of a server error when no public match exists', function () {
    $platform = Tenant::factory()->create();
    GlobalMarketFixture::assignCanada($platform);
    config()->set('ai.public.billing_tenant_id', (string) $platform->id);

    $response = postJson('/api/v1/directory/guide/chat', [
        'message' => 'Necesito algo que no existe',
        'budget' => 10,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('items', [])
        ->assertJsonPath('meta.llm_used', false)
        ->assertJsonPath('meta.privacy_scope', 'public_directory_only');
});
