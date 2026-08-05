<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class CanonicalProductWriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_ignores_hostile_body_tenant_and_uses_the_trusted_context(): void
    {
        [$owner, $tenant] = $this->ownerAndTenant();
        $hostile = Tenant::factory()->create();
        Sanctum::actingAs($owner);

        $this->actingAs($owner)
            ->withHeaders(['Idempotency-Key' => 'api-hostile-create', 'X-Tenant-ID' => (string) $tenant->getKey()])
            ->postJson('/api/v1/products', [
                'tenant_id' => (string) $hostile->getKey(),
                'name' => 'Producto seguro',
                'price' => '12.34',
                'stock_quantity' => 1,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('products', ['tenant_id' => $tenant->getKey(), 'name' => 'Producto seguro']);
        $this->assertDatabaseMissing('products', ['tenant_id' => $hostile->getKey(), 'name' => 'Producto seguro']);
    }

    public function test_update_ignores_hostile_body_tenant_and_never_changes_foreign_product(): void
    {
        [$owner, $tenant] = $this->ownerAndTenant();
        $foreignTenant = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $context->reset();
        $context->setTenant((string) $foreignTenant->getKey());
        $foreign = Product::factory()->forTenant($foreignTenant)->create(['name' => 'Ajeno']);
        $context->reset();
        $context->setTenant((string) $tenant->getKey());
        $product = Product::factory()->forTenant($tenant)->create(['name' => 'Antes']);
        Sanctum::actingAs($owner);

        $this->actingAs($owner)
            ->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->putJson('/api/v1/products/'.$product->getKey(), [
                'tenant_id' => (string) $foreignTenant->getKey(),
                'name' => 'Después',
                'price' => '20.00',
            ])
            ->assertOk();

        $this->assertSame('Después', $product->fresh()->name);
        $this->assertSame('Ajeno', $foreign->fresh()->name);
    }

    public function test_api_rejects_ambiguous_float_price_before_the_application_command(): void
    {
        [$owner, $tenant] = $this->ownerAndTenant();
        Sanctum::actingAs($owner);

        $this->actingAs($owner)
            ->withHeaders(['Idempotency-Key' => 'api-float-price', 'X-Tenant-ID' => (string) $tenant->getKey()])
            ->postJson('/api/v1/products', [
                'name' => 'Producto inseguro',
                'price' => 12.34,
            ])
            ->assertUnprocessable();
    }

    public function test_api_rejects_decimal_ten_two_overflow_on_create_and_update(): void
    {
        [$owner, $tenant] = $this->ownerAndTenant();
        $product = Product::factory()->forTenant($tenant)->create();
        Sanctum::actingAs($owner);
        $headers = ['X-Tenant-ID' => (string) $tenant->getKey(), 'Idempotency-Key' => 'api-price-overflow'];

        $this->withHeaders($headers)->postJson('/api/v1/products', [
            'name' => 'Precio inválido',
            'price' => '999999999.99',
        ])->assertUnprocessable();

        $this->withHeaders($headers)->putJson('/api/v1/products/'.$product->getKey(), [
            'name' => 'Precio inválido',
            'price' => '999999999.99',
        ])->assertUnprocessable();
    }

    public function test_create_requires_a_nonempty_idempotency_key(): void
    {
        [$owner, $tenant] = $this->ownerAndTenant();
        Sanctum::actingAs($owner);
        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/products', ['name' => 'Sin clave', 'price' => '1.00'])
            ->assertUnprocessable();
    }

    public function test_real_api_persists_explicit_unmanaged_stock(): void
    {
        [$owner, $tenant] = $this->ownerAndTenant();
        Sanctum::actingAs($owner);
        $this->withHeaders(['X-Tenant-ID' => (string) $tenant->getKey(), 'Idempotency-Key' => 'api-unmanaged'])
            ->postJson('/api/v1/products', ['name' => 'Servicio', 'price' => '10.00', 'manage_stock' => false])
            ->assertCreated();
        $this->assertDatabaseHas('products', ['tenant_id' => $tenant->getKey(), 'name' => 'Servicio', 'manage_stock' => false]);
    }

    public function test_api_normalizes_zero_manage_stock_and_rejects_initial_stock(): void
    {
        [$owner, $tenant] = $this->ownerAndTenant();
        Sanctum::actingAs($owner);
        $this->withHeaders(['X-Tenant-ID' => (string) $tenant->getKey(), 'Idempotency-Key' => 'api-unmanaged-stock'])
            ->postJson('/api/v1/products', ['name' => 'Servicio inválido', 'price' => '10.00', 'manage_stock' => 0, 'stock_quantity' => 1])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('products', ['tenant_id' => $tenant->getKey(), 'name' => 'Servicio inválido']);
    }

    public function test_real_api_stock_retry_appends_one_movement(): void
    {
        [$owner, $tenant] = $this->ownerAndTenant();
        $product = Product::factory()->forTenant($tenant)->withStock(0)->create();
        Sanctum::actingAs($owner);
        $headers = ['X-Tenant-ID' => (string) $tenant->getKey(), 'Idempotency-Key' => 'stock-api-retry'];
        $payload = ['name' => 'Stock API', 'price' => '1.00', 'stock_quantity' => 3];

        $this->withHeaders($headers)->putJson('/api/v1/products/'.$product->getKey(), $payload)->assertOk();
        $this->withHeaders($headers)->putJson('/api/v1/products/'.$product->getKey(), $payload)->assertOk();

        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->withHeaders($headers)->putJson('/api/v1/products/'.$product->getKey(), ['name' => 'No parcial', 'price' => '2.00', 'stock_quantity' => 4])
            ->assertUnprocessable();
        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $this->assertSame('Stock API', $product->fresh()->name);
        $this->assertSame('1.00', (string) $product->fresh()->price);
    }

    /** @return array{User, Tenant} */
    private function ownerAndTenant(): array
    {
        $context = app(TenantContext::class);
        $context->reset();
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['user_id' => $user->getKey()]);
        $user->forceFill(['tenant_id' => $tenant->getKey()])->save();
        $tenant->memberships()->create(['user_id' => $user->getKey(), 'role' => Role::OWNER, 'accepted_at' => now()]);
        GlobalMarketFixture::assignCanada($tenant);

        return [$user, $tenant];
    }
}
