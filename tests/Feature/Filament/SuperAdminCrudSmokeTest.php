<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Filament\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

final class SuperAdminCrudSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_user_from_filament_page(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(CreateUser::class)
            ->set('data.name', 'Soporte Operativo')
            ->set('data.email', 'soporte.operativo@example.com')
            ->set('data.role', UserRole::STAFF->value)
            ->set('data.password', 'password123')
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.admin.resources.users.index', [], false));

        $user = User::query()->where('email', 'soporte.operativo@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', (string) $user->password));
        $this->assertSame(UserRole::STAFF->value, $user->role->value);
    }

    public function test_superadmin_can_create_tenant_from_filament_page_and_owner_is_synced(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'tenant_id' => null,
        ]);
        $category = Category::factory()->create();
        $city = City::factory()->create();

        $this->actingAs($superAdmin);
        $this->setAdminPanelContext();

        Livewire::test(CreateTenant::class)
            ->set('data.name', 'Bistro Central')
            ->set('data.slug', 'bistro-central')
            ->set('data.user_id', (string) $owner->getKey())
            ->set('data.category_id', $category->getKey())
            ->set('data.business_type', BusinessType::RETAIL->value)
            ->set('data.city_id', $city->getKey())
            ->set('data.product_limit', 10)
            ->set('data.is_active', true)
            ->set('data.is_pro', false)
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.admin.resources.tenants.index', [], false));

        $tenant = Tenant::query()->where('slug', 'bistro-central')->first();

        $this->assertNotNull($tenant);
        $this->assertSame((string) $owner->getKey(), (string) $tenant->user_id);

        $owner->refresh();

        $this->assertSame((string) $tenant->getKey(), (string) $owner->tenant_id);
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
            'email' => 'superadmin@example.com',
        ]);

        config(['auth.super_admins' => [$superAdmin->email]]);

        return $superAdmin;
    }

    private function setAdminPanelContext(): void
    {
        Filament::setCurrentPanel('admin');
    }
}
