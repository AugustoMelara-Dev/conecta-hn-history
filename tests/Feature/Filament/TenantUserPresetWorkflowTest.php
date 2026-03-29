<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Access\Enums\Permission;
use App\Domain\Identity\Enums\UserRole;
use App\Filament\App\Resources\TenantUserResource\Pages\CreateTenantUser;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

final class TenantUserPresetWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_can_create_team_member_with_vertical_preset_permissions(): void
    {
        [$owner, $tenant] = $this->makeTenantOwner();
        $staff = User::factory()->create([
            'role' => UserRole::STAFF,
        ]);

        $this->actingAs($owner);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(CreateTenantUser::class)
            ->set('data.user_id', (string) $staff->getKey())
            ->set('data.role', 'viewer')
            ->set('data.preset_key', 'dental_reception')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.app.resources.tenant-users.index', ['tenant' => $tenant->slug], false));

        $membership = TenantMembership::query()->firstOrFail();

        $this->assertSame((string) $tenant->getKey(), (string) $membership->tenant_id);
        $this->assertSame((string) $staff->getKey(), (string) $membership->user_id);
        $this->assertSame('operator', $membership->role->value);
        $this->assertSame('dental_reception', $membership->preset_key);
        $this->assertContains(Permission::APPOINTMENTS_MANAGE->value, $membership->extraPermissions());
        $this->assertContains(Permission::MARKETING_MANAGE->value, $membership->extraPermissions());
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(): array
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Salud',
            'slug' => 'salud',
        ]);

        $tenant = Tenant::factory()->state([
            'user_id' => $owner->id,
            'slug' => 'clinica-sonrisa-equipo',
            'name' => 'Clinica Sonrisa Dental',
            'business_type' => 'service',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ])->pro()->create();

        $owner->forceFill(['tenant_id' => $tenant->id])->save();

        return [$owner, $tenant];
    }

    private function setAppPanelTenantContext(Tenant $tenant): void
    {
        Filament::setCurrentPanel('app');
        Filament::setTenant($tenant, isQuiet: true);
        $this->actingAsTenant((string) $tenant->getKey());
    }
}
