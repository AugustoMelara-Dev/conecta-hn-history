<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class LegacyWorkspaceWriteRedirectTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_product_routes_redirect_to_the_official_products_panel_without_writing(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('workspace-productos');

        $this->actingAs($user)
            ->get(route('tenant.products.create', [], false))
            ->assertRedirect(route('filament.app.resources.products.index', ['tenant' => $tenant->slug], false));

        $response = $this->actingAs($user)->post(route('tenant.products.store', [], false), [
            'name' => 'Producto fantasma',
            'price' => 10,
        ]);

        $response
            ->assertRedirect(route('filament.app.resources.products.index', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La gestion de productos ya se administra desde el panel oficial en /app.');

        $this->assertDatabaseMissing('products', [
            'tenant_id' => $tenant->id,
            'name' => 'Producto fantasma',
        ]);
    }

    public function test_legacy_settings_routes_redirect_to_the_official_settings_panel_without_writing(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('workspace-settings');

        $tenant->forceFill([
            'simple_schedule' => 'Lunes a Viernes 8:00 AM - 5:00 PM',
        ])->save();

        $response = $this->actingAs($user)->put(route('tenant.settings.update-schedule', [], false), [
            'schedule' => [
                'monday' => ['open' => '09:00', 'close' => '17:00'],
            ],
        ]);

        $response
            ->assertRedirect(route('filament.app.pages.settings', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La configuracion del negocio ya se administra desde el panel oficial en /app.');

        self::assertSame('Lunes a Viernes 8:00 AM - 5:00 PM', $tenant->fresh()?->simple_schedule);
    }

    public function test_legacy_product_mutation_routes_redirect_without_mutating_existing_records(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('workspace-productos-mutaciones');

        $product = Product::factory()
            ->forTenant($tenant)
            ->create([
                'name' => 'Producto estable',
                'price' => 1500,
                'is_visible' => true,
            ]);

        $this->actingAs($user)
            ->get(route('tenant.products.edit', ['product' => $product->id], false))
            ->assertRedirect(route('filament.app.resources.products.index', ['tenant' => $tenant->slug], false));

        $this->actingAs($user)
            ->put(route('tenant.products.update', ['product' => $product->id], false), [
                'name' => 'Producto editado desde legacy',
                'price' => 2000,
            ])
            ->assertRedirect(route('filament.app.resources.products.index', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La gestion de productos ya se administra desde el panel oficial en /app.');

        $this->actingAs($user)
            ->delete(route('tenant.products.destroy', ['product' => $product->id], false))
            ->assertRedirect(route('filament.app.resources.products.index', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La gestion de productos ya se administra desde el panel oficial en /app.');

        $this->actingAs($user)
            ->put(route('tenant.products.toggle-visibility', ['product' => $product->id], false))
            ->assertRedirect(route('filament.app.resources.products.index', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La gestion de productos ya se administra desde el panel oficial en /app.');

        $freshProduct = $product->fresh();
        self::assertInstanceOf(Product::class, $freshProduct);
        self::assertSame('Producto estable', $freshProduct->name);
        self::assertSame('1500.00', (string) $freshProduct->price);
        self::assertTrue((bool) $freshProduct->is_visible);
    }

    public function test_legacy_order_status_route_redirects_to_the_official_orders_panel_without_mutating(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('workspace-orders');

        $order = Order::factory()
            ->forTenant($tenant)
            ->create([
                'user_id' => $user->id,
                'status' => Order::STATUS_PENDING,
            ]);

        $response = $this->actingAs($user)->put(route('tenant.orders.update-status', ['order' => $order->id], false), [
            'status' => Order::STATUS_CONFIRMED,
        ]);

        $response
            ->assertRedirect(route('filament.app.resources.orders.index', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La gestion operativa de pedidos ya se administra desde el panel oficial en /app.');

        self::assertSame(Order::STATUS_PENDING, $order->fresh()?->status);
    }

    public function test_legacy_billing_write_routes_redirect_to_the_official_billing_panel_without_creating_payments(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('workspace-billing');

        $response = $this->actingAs($user)->post(route('tenant.billing.report-payment', [], false), [
            'reference_number' => 'LEGACY-001',
            'amount' => 1250,
            'bank' => 'BAC',
        ]);

        $response
            ->assertRedirect(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La gestion de pagos y upgrades ya se administra desde el panel oficial en /app.');

        $response = $this->actingAs($user)->post(route('tenant.billing.upgrade', [], false), [
            'plan' => 'pro',
        ]);

        $response
            ->assertRedirect(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La gestion de pagos y upgrades ya se administra desde el panel oficial en /app.');

        $this->assertDatabaseMissing('subscription_payments', [
            'tenant_id' => $tenant->id,
            'reference_number' => 'LEGACY-001',
        ]);
    }

    public function test_legacy_settings_profile_route_redirects_to_the_official_settings_panel_without_mutating(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('workspace-settings-profile');

        $tenant->forceFill([
            'name' => 'Nombre Original',
            'description' => 'Descripcion original',
        ])->save();

        $response = $this->actingAs($user)->post(route('tenant.settings.update-profile', [], false), [
            'name' => 'Nombre editado desde legacy',
            'description' => 'Descripcion editada desde legacy',
        ]);

        $response
            ->assertRedirect(route('filament.app.pages.settings', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('error', 'La configuracion del negocio ya se administra desde el panel oficial en /app.');

        $freshTenant = $tenant->fresh();
        self::assertInstanceOf(Tenant::class, $freshTenant);
        self::assertSame('Nombre Original', $freshTenant->name);
        self::assertSame('Descripcion original', $freshTenant->description);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(string $slug): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => $slug,
        ]);

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }
}
