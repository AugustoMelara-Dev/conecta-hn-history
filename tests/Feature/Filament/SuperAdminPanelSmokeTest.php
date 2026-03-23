<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SuperAdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_route_matrix_is_accessible_without_server_errors(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        foreach ($this->routeMatrix() as $routeName => $parameters) {
            $response = $this->get(route($routeName, $parameters, false));

            $this->assertTrue(
                $response->isOk(),
                sprintf('La ruta admin [%s] devolvio HTTP %s.', $routeName, $response->getStatusCode())
            );
        }
    }

    public function test_superadmin_key_pages_render_spanish_copy(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        $this->get(route('filament.admin.pages.dashboard', [], false))
            ->assertOk()
            ->assertSee('Centro de control de la plataforma')
            ->assertSee('Atencion inmediata');

        $this->get(route('filament.admin.pages.manage-general-settings', [], false))
            ->assertOk()
            ->assertSee('Configuracion general')
            ->assertSee('Guardar cambios');

        $this->get(route('filament.admin.pages.billing', [], false))
            ->assertOk()
            ->assertSee('Facturacion de la plataforma')
            ->assertSee('Ultimos pagos reportados');
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function routeMatrix(): array
    {
        return [
            'filament.admin.pages.dashboard' => [],
            'filament.admin.pages.manage-general-settings' => [],
            'filament.admin.pages.billing' => [],
            'filament.admin.pages.architecture-health' => [],
            'filament.admin.resources.tenants.index' => [],
            'filament.admin.resources.tenants.create' => [],
            'filament.admin.resources.users.index' => [],
            'filament.admin.resources.users.create' => [],
            'filament.admin.resources.payments.index' => [],
            'filament.admin.resources.categories.index' => [],
            'filament.admin.resources.categories.create' => [],
            'filament.admin.resources.cities.index' => [],
            'filament.admin.resources.cities.create' => [],
        ];
    }

    private function makeSuperAdmin(): User
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'email' => 'panel-superadmin@example.com',
        ]);

        config(['auth.super_admins' => [$superAdmin->email]]);

        Tenant::factory()->create();

        return $superAdmin;
    }
}
