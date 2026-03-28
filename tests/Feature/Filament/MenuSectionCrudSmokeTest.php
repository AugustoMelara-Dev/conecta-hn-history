<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\App\Resources\MenuSectionResource\Pages\CreateMenuSection;
use App\Filament\App\Resources\MenuSectionResource\Pages\EditMenuSection;
use App\Models\Category;
use App\Models\City;
use App\Models\MenuSection;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

final class MenuSectionCrudSmokeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_can_create_menu_section_from_filament_resource_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner();

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(CreateMenuSection::class)
            ->set('data.name', 'Promociones')
            ->set('data.is_active', true)
            ->call('create')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.app.resources.menu-sections.index', ['tenant' => $tenant->slug], false));

        $menuSection = MenuSection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', 'Promociones')
            ->firstOrFail();

        self::assertSame((string) $tenant->getKey(), (string) $menuSection->tenant_id);
        self::assertSame(1, (int) $menuSection->sort_order);
        self::assertTrue((bool) $menuSection->is_active);
    }

    public function test_owner_can_edit_menu_section_from_filament_resource_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner();
        $menuSection = $this->makeMenuSection($tenant, 'Bebidas', sortOrder: 1);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(EditMenuSection::class, ['record' => $menuSection->getKey()])
            ->set('data.name', 'Bebidas frías')
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.app.resources.menu-sections.index', ['tenant' => $tenant->slug], false));

        $menuSection->refresh();

        self::assertSame('Bebidas frías', $menuSection->name);
        self::assertFalse((bool) $menuSection->is_active);
        self::assertSame((string) $tenant->getKey(), (string) $menuSection->tenant_id);
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
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create();

        $tenant = Tenant::factory()->pro()->create([
            'user_id' => $user->id,
            'slug' => 'menu-section-crud',
            'business_type' => 'food',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }

    private function setAppPanelTenantContext(Tenant $tenant): void
    {
        Filament::setCurrentPanel('app');
        Filament::setTenant($tenant, isQuiet: true);
        $this->actingAsTenant((string) $tenant->getKey());
    }

    private function makeMenuSection(Tenant $tenant, string $name, int $sortOrder, bool $isActive = true): MenuSection
    {
        $menuSection = new MenuSection;
        $menuSection->forceFill([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => $name,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ]);
        $menuSection->save();

        return $menuSection->fresh() ?? $menuSection;
    }
}
