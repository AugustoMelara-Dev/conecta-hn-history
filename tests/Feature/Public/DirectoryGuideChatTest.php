<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('answers public directory guide questions with only public catalog data', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Comedor La Ceiba',
        'slug' => 'comedor-la-ceiba',
        'is_active' => true,
        'subscription_status' => 'active',
    ]);

    $product = Product::factory()
        ->forTenant($tenant)
        ->make([
            'name' => 'Baleada con cafe',
            'description' => 'Comida tipica economica',
            'price' => 95,
            'is_visible' => true,
            'status' => 'active',
        ]);
    $attributes = collect($product->getAttributes())
        ->filter(fn ($value, string $key): bool => Schema::hasColumn('products', $key))
        ->all();

    $persistedProduct = new Product();
    $persistedProduct->forceFill($attributes);
    $persistedProduct->save();

    $response = $this->postJson('/api/v1/directory/guide/chat', [
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
            'price_label' => 'L. 95.00',
        ]);
});

it('returns an honest fallback instead of a server error when no public match exists', function () {
    $response = $this->postJson('/api/v1/directory/guide/chat', [
        'message' => 'Necesito algo que no existe',
        'budget' => 10,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('items', [])
        ->assertJsonPath('meta.llm_used', false)
        ->assertJsonPath('meta.privacy_scope', 'public_directory_only');
});
