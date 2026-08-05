<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Http\Controllers\Tenant\LegacyWorkspaceActionController;
use App\Infrastructure\Http\Middleware\IdentifyTenantMiddleware;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class LegacyWorkspaceActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, array<string, string>>
     */
    private const ROUTES = [
        'tenant.products.store' => [],
        'tenant.products.update' => ['product' => 'hostile-product'],
        'tenant.products.destroy' => ['product' => 'hostile-product'],
        'tenant.products.toggle-visibility' => ['product' => 'hostile-product'],
        'tenant.orders.update-status' => ['order' => 'hostile-order'],
        'orders.print-ticket' => ['order' => 'hostile-order'],
        'receipt.download' => ['order' => 'hostile-order'],
        'tenant.billing.report-payment' => [],
        'tenant.billing.upgrade' => [],
        'tenant.settings.update-profile' => [],
        'tenant.settings.update-schedule' => [],
        'reports.sales-pdf' => [],
        'reports.inventory-pdf' => [],
        'tenant.coupons.store' => [],
        'tenant.coupons.toggle' => ['coupon' => 'hostile-coupon'],
        'tenant.landing-page.update' => [],
    ];

    public function test_all_legacy_actions_keep_authentication_verification_and_tenant_guards(): void
    {
        foreach (self::ROUTES as $name => $parameters) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertInstanceOf(IlluminateRoute::class, $route, "Missing compatibility route [{$name}].");
            $this->assertSame(LegacyWorkspaceActionController::class, $route->getAction('controller'));
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('verified', $route->gatherMiddleware());
            $this->assertContains(IdentifyTenantMiddleware::class, $route->gatherMiddleware());

            $this->json($this->httpMethod($route), route($name, $parameters, false))
                ->assertUnauthorized();
        }
    }

    public function test_authenticated_tenant_receives_gone_for_every_legacy_action_in_json(): void
    {
        [$user] = $this->tenantUser('compatibility-owner');

        foreach (self::ROUTES as $name => $parameters) {
            $route = Route::getRoutes()->getByName($name);

            $this->actingAs($user)
                ->json($this->httpMethod($route), route($name, $parameters, false))
                ->assertGone()
                ->assertJsonStructure(['message']);
        }
    }

    public function test_hostile_cross_tenant_product_id_cannot_mutate_the_product(): void
    {
        [$user] = $this->tenantUser('tenant-a');
        [, $otherTenant] = $this->tenantUser('tenant-b');
        $product = Product::factory()->forTenant($otherTenant)->create(['name' => 'Original name']);

        $this->actingAs($user)
            ->putJson(route('tenant.products.update', ['product' => $product->id], false), [
                'name' => 'Hostile update',
            ])
            ->assertGone();

        $this->assertSame(
            'Original name',
            DB::table('products')->where('id', $product->id)->value('name')
        );
    }

    public function test_retired_downloads_redirect_to_read_only_canonical_sections_for_html(): void
    {
        [$user] = $this->tenantUser('redirect-owner');

        $this->actingAs($user)
            ->get(route('orders.print-ticket', ['order' => 'any-order'], false))
            ->assertRedirect('/app/redirect-owner/orders');

        $this->actingAs($user)
            ->get(route('receipt.download', ['order' => 'any-order'], false))
            ->assertRedirect('/app/redirect-owner/orders');

        $this->actingAs($user)
            ->get(route('reports.sales-pdf', absolute: false))
            ->assertRedirect('/app/redirect-owner');

        $this->actingAs($user)
            ->get(route('reports.inventory-pdf', absolute: false))
            ->assertRedirect('/app/redirect-owner');
    }

    private function httpMethod(IlluminateRoute $route): string
    {
        return collect($route->methods())->first(fn (string $method): bool => $method !== 'HEAD') ?? 'GET';
    }

    /**
     * @return array{User, Tenant}
     */
    private function tenantUser(string $slug): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create(['slug' => $slug]);
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user->refresh(), $tenant];
    }
}
