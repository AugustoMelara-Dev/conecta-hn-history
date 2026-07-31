<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class DirectoryGuideChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($platform);
        config()->set('ai.public.billing_tenant_id', (string) $platform->id);
    }

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
            ->assertJsonStructure([
                'conversation_id',
                'request_message_id',
                'response_message_id',
                'status',
                'code',
            ])
            ->assertJsonPath('source', 'public_directory')
            ->assertJsonPath('fallback', true)
            ->assertJsonFragment(['name' => 'Cafe frio'])
            ->assertJsonMissing(['name' => 'Canasta premium']);
    }

    public function test_public_directory_guide_returns_services_and_excludes_unavailable_stock(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Bienestar Centro',
            'slug' => 'bienestar-centro',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);

        $service = Service::factory()->for($tenant)->create([
            'name' => 'Masaje breve',
            'description' => 'Servicio publico',
            'price' => 120,
            'is_active' => true,
        ]);

        Product::factory()->forTenant($tenant)->outOfStock()->create([
            'name' => 'Aceite agotado',
            'price' => 80,
            'is_active' => true,
            'is_visible' => true,
            'allow_backorders' => false,
        ]);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'masaje con 150 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'evidence_id' => 'service:'.$service->id,
                'name' => 'Masaje breve',
                'availability' => 'available',
            ])
            ->assertJsonMissing(['name' => 'Aceite agotado']);

        self::assertStringNotContainsString('stock_quantity', $response->getContent());
        self::assertStringNotContainsString('tenant_id', $response->getContent());
    }

    public function test_inferred_budget_over_the_public_limit_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'comprar con '.str_repeat('9', 400).' lempiras',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('message');
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

    public function test_active_tenant_without_active_subscription_is_excluded(): void
    {
        if (! Schema::hasColumn('products', 'status')) {
            Schema::table('products', static function (Blueprint $table): void {
                $table->string('status')->nullable();
            });
        }

        $active = Tenant::factory()->create([
            'name' => 'Suscripcion Activa',
            'slug' => 'suscripcion-activa',
            'is_active' => true,
            'subscription_status' => 'active',
        ]);
        $cancelled = Tenant::factory()->create([
            'name' => 'Suscripcion Cancelada',
            'slug' => 'suscripcion-cancelada',
            'is_active' => true,
            'subscription_status' => 'canceled',
        ]);

        Product::factory()->forTenant($active)->create([
            'name' => 'Producto publicado',
            'price' => 20,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);
        Product::factory()->forTenant($cancelled)->create([
            'name' => 'Producto sin suscripcion',
            'price' => 10,
            'status' => 'active',
            'is_active' => true,
            'is_visible' => true,
        ]);
        Product::factory()->forTenant($active)->create([
            'name' => 'Producto sin publicar',
            'price' => 15,
            'status' => null,
            'is_active' => true,
            'is_visible' => true,
        ]);

        $response = $this->postJson('/api/v1/directory/guide/chat', [
            'message' => 'producto con 50 lempiras',
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment(['name' => 'Producto publicado'])
            ->assertJsonMissing(['name' => 'Producto sin suscripcion'])
            ->assertJsonMissing(['name' => 'Producto sin publicar']);
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
