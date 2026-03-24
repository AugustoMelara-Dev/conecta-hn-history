<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class SuperAdminImpersonationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_impersonate_a_business_owner_and_return_to_support_mode(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        [$owner, $tenant] = $this->makeTenantOwner();

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(ListTenants::class)
            ->callTableAction('impersonate', $tenant)
            ->assertHasNoTableActionErrors()
            ->assertRedirect(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false));

        $this->assertSame((string) $superAdmin->getKey(), (string) session('impersonator_id'));
        $this->assertSame($superAdmin->name, session('impersonator_name'));
        $this->assertSame((string) $owner->getKey(), (string) auth()->id());

        $this->get(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Escritorio')
            ->assertSee('Panel del negocio');

        $this->get(route('impersonate.leave'))
            ->assertRedirect('/admin');

        $this->assertNull(session('impersonator_id'));
        $this->assertNull(session('impersonator_name'));
        $this->assertSame((string) $superAdmin->getKey(), (string) auth()->id());
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    private function makeSuperAdmin(): User
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'email' => 'support.superadmin@example.com',
        ]);

        config(['auth.super_admins' => [$superAdmin->email]]);

        return $superAdmin;
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(): array
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'email' => 'dueno.soporte@example.com',
        ]);

        $tenant = Tenant::factory()->create([
            'user_id' => $owner->getKey(),
            'slug' => 'negocio-soporte-qa',
            'business_type' => 'retail',
        ]);

        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        return [$owner, $tenant];
    }

    private function setAdminPanelContext(): void
    {
        Filament::setCurrentPanel('admin');
    }
}
