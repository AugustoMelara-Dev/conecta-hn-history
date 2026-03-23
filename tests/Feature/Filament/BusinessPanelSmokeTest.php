<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BusinessPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_retail_owner_route_matrix_matches_expected_access(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false, businessType: 'retail', slug: 'cafe-norte-free');

        $this->actingAs($user);
        $this->actingAsTenant((string) $tenant->getKey());

        foreach ($this->freeRetailRouteMatrix() as $routeName => $expectedStatus) {
            $response = $this->get(route($routeName, ['tenant' => $tenant->slug], false));

            $this->assertSame(
                $expectedStatus,
                $response->getStatusCode(),
                sprintf('La ruta [%s] devolvio HTTP %s y se esperaba %s.', $routeName, $response->getStatusCode(), $expectedStatus)
            );
        }
    }

    public function test_pro_retail_owner_can_open_pro_modules_without_server_errors(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: true, businessType: 'retail', slug: 'cafe-norte-pro');

        $this->actingAs($user);
        $this->actingAsTenant((string) $tenant->getKey());

        foreach ($this->proRetailRoutes() as $routeName) {
            $response = $this->get(route($routeName, ['tenant' => $tenant->slug], false));

            $this->assertTrue(
                $response->isOk(),
                sprintf('La ruta PRO [%s] devolvio HTTP %s.', $routeName, $response->getStatusCode())
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function freeRetailRouteMatrix(): array
    {
        return [
            'filament.app.pages.dashboard' => 200,
            'filament.app.pages.settings' => 200,
            'filament.app.pages.manage-marketing' => 200,
            'filament.app.pages.manage-billing' => 200,
            'filament.app.pages.upgrade-to-pro' => 200,
            'filament.app.resources.followers.index' => 200,
            'filament.app.resources.leads.index' => 200,
            'filament.app.resources.leads.create' => 200,
            'filament.app.resources.orders.index' => 200,
            'filament.app.resources.products.index' => 200,
            'filament.app.resources.products.create' => 200,
            'filament.app.resources.inventory-levels.index' => 200,
            'filament.app.resources.inventory-movements.index' => 200,
            'filament.app.resources.campaigns.index' => 403,
            'filament.app.resources.campaigns.create' => 403,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function proRetailRoutes(): array
    {
        return [
            'filament.app.pages.dashboard',
            'filament.app.pages.settings',
            'filament.app.pages.manage-marketing',
            'filament.app.pages.manage-billing',
            'filament.app.pages.upgrade-to-pro',
            'filament.app.resources.campaigns.index',
            'filament.app.resources.campaigns.create',
            'filament.app.resources.products.index',
            'filament.app.resources.products.create',
            'filament.app.resources.leads.index',
            'filament.app.resources.leads.create',
            'filament.app.resources.orders.index',
            'filament.app.resources.followers.index',
            'filament.app.resources.inventory-levels.index',
            'filament.app.resources.inventory-movements.index',
        ];
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(bool $isPro, string $businessType, string $slug): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $tenantFactory = Tenant::factory()->state([
            'user_id' => $user->id,
            'slug' => $slug,
            'business_type' => $businessType,
        ]);

        if ($isPro) {
            $tenantFactory = $tenantFactory->pro();
        }

        $tenant = $tenantFactory->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }
}
