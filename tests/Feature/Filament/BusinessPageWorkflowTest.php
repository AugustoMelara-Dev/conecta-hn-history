<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\App\Pages\ManageMarketing;
use App\Filament\App\Pages\Settings;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class BusinessPageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_response_includes_critical_styles_and_spanish_copy(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false, slug: 'cafe-norte-dashboard');

        $response = $this
            ->actingAs($user)
            ->actingAsTenant((string) $tenant->getKey())
            ->get(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false));

        $response->assertOk();
        $response->assertSee('data-filament-critical-theme', false);
        $response->assertSee('Panel del negocio');
        $response->assertSee('Gestiona tu catalogo');
    }

    public function test_free_owner_pages_render_spanish_empty_states_and_upgrade_copy(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false, slug: 'cafe-norte-flujo');

        $this->actingAs($user);
        $this->actingAsTenant((string) $tenant->getKey());

        $this->get(route('filament.app.pages.manage-marketing', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Impulsa tu negocio con QR y WhatsApp')
            ->assertSee('Marketing PRO');

        $this->get(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Activa tu plan PRO')
            ->assertSee('Enviar pago a revision');

        $this->get(route('filament.app.pages.upgrade-to-pro', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Desbloquea mas herramientas para tu negocio')
            ->assertSee('Ir a suscripcion');

        $this->get(route('filament.app.resources.followers.index', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('La lista de seguidores se desbloquea con PRO');

        $this->get(route('filament.app.resources.leads.index', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Todavia no tienes prospectos');
    }

    public function test_pro_owner_pages_render_operational_copy_for_marketing_and_billing(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: true, slug: 'cafe-norte-flujo-pro');

        $this->actingAs($user);
        $this->actingAsTenant((string) $tenant->getKey());

        $this->get(route('filament.app.pages.manage-marketing', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Mensaje de contacto')
            ->assertSee('Codigo QR');

        $this->get(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Suscripcion activa')
            ->assertSee('Pagos registrados');
    }

    public function test_owner_can_save_business_settings_from_filament_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false, slug: 'cafe-norte-settings');

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(Settings::class)
            ->set('data.name', 'Cafe Norte Renovado')
            ->set('data.whatsapp_number', '9999-2222')
            ->set('data.description', 'Cafe de especialidad y desayunos.')
            ->set('data.address', 'Boulevard Centro, Tegucigalpa')
            ->set('data.whatsapp_message', 'Hola, quiero informacion de {business_name}.')
            ->call('save')
            ->assertHasNoErrors();

        $tenant->refresh();

        $this->assertSame('Cafe Norte Renovado', $tenant->name);
        $this->assertSame('9999-2222', $tenant->whatsapp_number);
        $this->assertSame('Boulevard Centro, Tegucigalpa', $tenant->address);
        $this->assertSame('Hola, quiero informacion de {business_name}.', $tenant->whatsapp_message);
    }

    public function test_pro_owner_can_save_marketing_whatsapp_configuration(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: true, slug: 'cafe-norte-marketing');

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(ManageMarketing::class)
            ->set('data.whatsapp_number', '9876-5432')
            ->set('data.whatsapp_message', 'Hola, me interesa {producto} por {precio}.')
            ->call('saveWhatsAppConfig')
            ->assertHasNoErrors();

        $tenant->refresh();

        $this->assertSame('98765432', $tenant->whatsapp_number);
        $this->assertSame('Hola, me interesa {producto} por {precio}.', $tenant->whatsapp_message);
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
    private function makeTenantOwner(bool $isPro, string $slug): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create();

        $tenantFactory = Tenant::factory()->state([
            'user_id' => $user->id,
            'slug' => $slug,
            'business_type' => 'retail',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ]);

        if ($isPro) {
            $tenantFactory = $tenantFactory->pro();
        }

        $tenant = $tenantFactory->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }

    private function setAppPanelTenantContext(Tenant $tenant): void
    {
        Filament::setCurrentPanel('app');
        Filament::setTenant($tenant, isQuiet: true);
        $this->actingAsTenant((string) $tenant->getKey());
    }
}
