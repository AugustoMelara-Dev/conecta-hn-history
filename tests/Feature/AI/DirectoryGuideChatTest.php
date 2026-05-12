<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DirectoryGuideChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_guide_answers_from_public_products_with_budget(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Cafe Norte',
            'slug' => 'cafe-norte',
            'is_active' => true,
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Cafe frio',
            'slug' => 'cafe-frio',
            'price' => 95,
            'is_active' => true,
            'is_visible' => true,
        ]);

        Product::factory()->forTenant($tenant)->create([
            'name' => 'Canasta premium',
            'slug' => 'canasta-premium',
            'price' => 350,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'que puedo comprar con 150 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('source', 'public_directory')
            ->assertJsonPath('fallback', true)
            ->assertJsonFragment(['name' => 'Cafe frio'])
            ->assertJsonMissing(['name' => 'Canasta premium']);
    }

    public function test_public_directory_guide_never_returns_private_sales_data(): void
    {
        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'cuanto vendio Cafe Norte hoy',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('source', 'public_directory')
            ->assertJsonMissing(['orders'])
            ->assertJsonMissing(['total_sales']);
    }

    public function test_public_directory_guide_excludes_hidden_inactive_and_inactive_tenant_products(): void
    {
        $activeTenant = Tenant::factory()->create([
            'name' => 'Mercado Claro',
            'slug' => 'mercado-claro',
            'is_active' => true,
        ]);

        $inactiveTenant = Tenant::factory()->inactive()->create([
            'name' => 'Mercado Cerrado',
            'slug' => 'mercado-cerrado',
            'is_active' => false,
        ]);

        Product::factory()->forTenant($activeTenant)->create([
            'name' => 'Pan visible',
            'slug' => 'pan-visible',
            'price' => 45,
            'is_active' => true,
            'is_visible' => true,
        ]);

        Product::factory()->forTenant($activeTenant)->create([
            'name' => 'Pan oculto',
            'slug' => 'pan-oculto',
            'price' => 40,
            'is_active' => true,
            'is_visible' => false,
        ]);

        Product::factory()->forTenant($activeTenant)->create([
            'name' => 'Pan pausado',
            'slug' => 'pan-pausado',
            'price' => 35,
            'is_active' => false,
            'is_visible' => true,
        ]);

        Product::factory()->forTenant($inactiveTenant)->create([
            'name' => 'Pan privado',
            'slug' => 'pan-privado',
            'price' => 30,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'que puedo comprar con 50 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment(['name' => 'Pan visible'])
            ->assertJsonMissing(['name' => 'Pan oculto'])
            ->assertJsonMissing(['name' => 'Pan pausado'])
            ->assertJsonMissing(['name' => 'Pan privado']);
    }
}
