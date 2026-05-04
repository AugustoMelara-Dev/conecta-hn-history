<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Filament\App\Pages\EditTenantProfile;
use App\Filament\App\Pages\GrowthCenter;
use App\Filament\App\Pages\ManageBilling;
use App\Filament\App\Pages\ManageMarketing;
use App\Filament\App\Pages\Settings;
use App\Filament\App\Resources\AppointmentResource;
use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\CustomerTouchpointResource;
use App\Filament\App\Resources\MenuSectionResource;
use App\Filament\App\Resources\OperationalPlaybookTaskResource;
use App\Filament\App\Resources\ServiceResource;
use App\Filament\App\Resources\TenantUserResource;
use App\Models\Appointment;
use App\Models\Category;
use App\Models\City;
use App\Models\Follow;
use App\Models\Lead;
use App\Models\LoyaltyProgram;
use App\Models\MenuSection;
use App\Models\OpeningHour;
use App\Models\OperationalPlaybookTask;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantAnalytic;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class BusinessPageWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_response_includes_panel_theme_and_retail_playbook(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false, slug: 'cafe-norte-dashboard');

        $response = $this
            ->actingAs($user)
            ->actingAsTenant((string) $tenant->getKey())
            ->get(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false));

        $response->assertOk();
        $response->assertSee('data-panel-theme-link="app-panel"', false);
        $response->assertSee('data-filament-critical-theme="true"', false);
        $response->assertDontSee('__filament_runtime', false);
        $response->assertSee('Panel del negocio');
        $response->assertSee('Checklist inicial');
        $response->assertSee('Workspace inicial');
        $response->assertSee('Automatizaciones comerciales');
    }

    public function test_service_business_dashboard_and_navigation_adapt_to_service_workflow(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: true,
            slug: 'estudio-juridico-dashboard',
            businessType: 'service',
        );

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Servicios y citas')
            ->assertSee('Catálogo de servicios')
            ->assertSee('Agenda de citas')
            ->assertSee('Workspace inicial');

        $this->get(ServiceResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk();

        $this->get(AppointmentResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk();
    }

    public function test_dental_clinic_dashboard_uses_segment_specific_context(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: true,
            slug: 'clinica-sonrisa-dashboard',
            businessType: 'service',
            tenantState: [
                'name' => 'Clinica Sonrisa Dental',
            ],
            categoryState: [
                'name' => 'Salud',
                'slug' => 'salud',
            ],
        );

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Equipo recomendado para clinica dental')
            ->assertSee('Seguimiento post-consulta');

        $this->get(TenantUserResource::getUrl('create', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('Recepcion clinica')
            ->assertSee('Coordinacion clinica');
    }

    public function test_food_business_dashboard_prioritizes_menu_and_order_workflows(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: false,
            slug: 'comedor-central-dashboard',
            businessType: 'food',
        );

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Restaurante y comida')
            ->assertSee('Editar menú')
            ->assertSee('Frentes criticos para cocina y venta');

        $this->get(MenuSectionResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk();
    }

    public function test_premium_phone_store_dashboard_uses_retail_segment_context(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: true,
            slug: 'flagship-mobile-dashboard',
            businessType: 'retail',
            tenantState: [
                'name' => 'Flagship Mobile',
                'description' => 'Equipos flagship, bundles y soporte premium.',
            ],
            categoryState: [
                'name' => 'Tecnologia',
                'slug' => 'tecnologia',
            ],
        );

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Equipo recomendado para celulares premium')
            ->assertSee('Seguimiento de alto ticket');
    }

    public function test_owner_can_open_operational_playbook_center_and_see_live_task(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: true,
            slug: 'flagship-mobile-playbooks',
            businessType: 'retail',
            tenantState: [
                'name' => 'Flagship Mobile',
            ],
            categoryState: [
                'name' => 'Tecnologia',
                'slug' => 'tecnologia',
            ],
        );

        OperationalPlaybookTask::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'assigned_user_id' => (string) $user->getKey(),
            'trigger_key' => 'lead_follow_up',
            'queue' => 'leads',
            'route_key' => 'leads',
            'segment_key' => 'premium_phone_store',
            'priority' => OperationalPlaybookTask::PRIORITY_HIGH,
            'status' => OperationalPlaybookTask::STATUS_OPEN,
            'sla_minutes' => 30,
            'title' => 'Responder cotizacion premium',
            'summary' => 'Lead listo para mover a discovery comercial.',
            'playbook_steps' => ['Responder al lead hoy mismo.'],
            'due_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.dashboard', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Mesa operativa con SLA')
            ->assertSee('Responder cotizacion premium')
            ->assertSee('Responsable: '.$user->name);

        $this->get(OperationalPlaybookTaskResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('Centro operativo')
            ->assertSee('Responder cotizacion premium');
    }

    public function test_free_owner_pages_render_spanish_empty_states_and_upgrade_copy(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false, slug: 'cafe-norte-flujo');

        $this->actingAs($user);
        $this->actingAsTenant((string) $tenant->getKey());

        $this->get(route('filament.app.pages.manage-marketing', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Impulsa tu negocio con QR y WhatsApp')
            ->assertSee('Marketing PRO')
            ->assertSee('Activar plan PRO');

        $this->get(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Activa tu plan PRO')
            ->assertSee('Enviar pago a revisión');

        $this->get(route('filament.app.pages.upgrade-to-pro', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Desbloquea más herramientas para tu negocio')
            ->assertSee('Activar PRO');

        $this->get(route('filament.app.resources.followers.index', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('La lista de seguidores se desbloquea con PRO');

        $this->get(route('filament.app.resources.leads.index', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSeeText('Todavia no tienes prospectos');
    }

    public function test_pro_owner_pages_render_operational_copy_for_marketing_and_billing(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: true, slug: 'cafe-norte-flujo-pro');

        $this->actingAs($user);
        $this->actingAsTenant((string) $tenant->getKey());

        $this->get(route('filament.app.pages.manage-marketing', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Mensaje de contacto')
            ->assertSee('Código QR')
            ->assertSee('Ver página pública');

        $this->get(route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Suscripción activa')
            ->assertSee('Pagos registrados');
    }

    public function test_growth_center_connects_crm_analytics_and_automations_for_retail(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: true,
            slug: 'flagship-mobile-growth',
            businessType: 'retail',
            tenantState: [
                'name' => 'Flagship Mobile',
                'whatsapp_number' => '98765432',
            ],
            categoryState: [
                'name' => 'Tecnologia',
                'slug' => 'tecnologia',
            ],
        );

        Lead::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '9999-1111',
            'source' => 'whatsapp_click',
            'status' => 'new',
        ]);

        Lead::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Luis',
            'email' => 'luis@example.com',
            'phone' => '9999-2222',
            'source' => 'catalog',
            'status' => 'closed',
        ]);

        Order::factory()
            ->forTenant($tenant)
            ->delivered()
            ->create([
                'user_id' => $user->getKey(),
                'customer_name' => 'Ana',
                'customer_email' => 'ana@example.com',
                'customer_phone' => '9999-1111',
                'total' => 450.00,
                'subtotal' => 450.00,
                'discount' => 0.00,
                'delivery_fee' => 0.00,
                'source' => 'whatsapp',
                'created_at' => now()->subDays(3),
            ]);

        Order::factory()
            ->forTenant($tenant)
            ->delivered()
            ->create([
                'user_id' => $user->getKey(),
                'customer_name' => 'Ana',
                'customer_email' => 'ana@example.com',
                'customer_phone' => '9999-1111',
                'total' => 390.00,
                'subtotal' => 390.00,
                'discount' => 0.00,
                'delivery_fee' => 0.00,
                'source' => 'web',
                'created_at' => now()->subDays(9),
            ]);

        $follower = User::factory()->create();

        Follow::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $follower->getKey(),
        ]);

        LoyaltyProgram::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Club Flagship',
            'earn_ratio' => 10,
            'redemption_ratio' => 100,
            'expiry_days' => 365,
            'is_active' => true,
        ]);

        TenantAnalytic::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'date' => today(),
            'unique_visitors' => 40,
            'views' => 68,
        ]);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.growth-center', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Centro de crecimiento')
            ->assertSee('Abrir clientes')
            ->assertSee('Ver contactos')
            ->assertSee('Bandeja de respuestas')
            ->assertSee('Automatizaciones externas')
            ->assertSee('Cohortes y LTV')
            ->assertSee('Comparativa por sede')
            ->assertSee('Oferta top')
            ->assertSee('Recurrencia')
            ->assertSee('Entrega de email')
            ->assertSee('Fidelidad para clientes frecuentes')
            ->assertSee('L. 840.00')
            ->assertSee('Clientes que vuelven');

        $this->get(CustomerProfileResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('CRM de clientes')
            ->assertSee('Ana');

        $this->get(CustomerTouchpointResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('Historial de contactos')
            ->assertSee('Plantilla')
            ->assertSee('Intentos');

        $this->get(route('filament.app.pages.customer-inbox', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Bandeja de respuestas');
    }

    public function test_growth_center_adapts_to_dental_clinic_playbook(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: true,
            slug: 'clinica-sonrisa-growth',
            businessType: 'service',
            tenantState: [
                'name' => 'Clinica Sonrisa Dental',
                'whatsapp_number' => '99998888',
            ],
            categoryState: [
                'name' => 'Salud',
                'slug' => 'salud',
            ],
        );

        Lead::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Paola',
            'email' => 'paola@example.com',
            'phone' => '9888-1212',
            'source' => 'website',
            'status' => 'contacted',
        ]);

        Appointment::withoutEvents(function () use ($tenant): void {
            Appointment::factory()
                ->confirmed()
                ->create([
                    'tenant_id' => $tenant->getKey(),
                    'customer_name' => 'Paola',
                    'customer_email' => 'paola@example.com',
                    'customer_phone' => '9888-1212',
                    'customer_notes' => 'Valoracion inicial',
                    'scheduled_at' => now()->addDays(3),
                    'end_at' => now()->addDays(3)->addHour(),
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ]);
        });

        TenantAnalytic::query()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'date' => today(),
            'unique_visitors' => 18,
            'views' => 27,
        ]);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $this->get(route('filament.app.pages.growth-center', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Automatizaciones externas para clinica dental')
            ->assertSee('Confirmar continuidad del servicio')
            ->assertSee('Reactivar clientes dormidos')
            ->assertSee('Pacientes para control e higiene')
            ->assertSee('Agenda lista')
            ->assertSee('Cohortes y LTV')
            ->assertSee('Entrega de email');
    }

    public function test_growth_center_renders_pending_badge_instead_of_clickable_hash_cta(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: true,
            slug: 'growth-center-pending-cta',
            businessType: 'retail',
        );

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(GrowthCenter::class)
            ->set('growthActions', [
                [
                    'title' => 'Accion sin ruta',
                    'copy' => 'CTA pendiente.',
                    'href' => '#',
                ],
            ])
            ->assertSee('Pendiente de configuracion')
            ->assertDontSee('href="#"', false);
    }

    public function test_owner_can_save_business_settings_from_filament_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false, slug: 'cafe-norte-settings');

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(Settings::class)
            ->set('data.name', 'Cafe Norte Renovado')
            ->set('data.whatsapp_number', '9999-2222')
            ->set('data.description', 'Café de especialidad y desayunos.')
            ->set('data.address', 'Boulevard Centro, Tegucigalpa')
            ->set('data.whatsapp_message', 'Hola, quiero información de {business_name}.')
            ->call('save')
            ->assertHasNoErrors();

        $tenant->refresh();

        $this->assertSame('Cafe Norte Renovado', $tenant->name);
        $this->assertSame('9999-2222', $tenant->whatsapp_number);
        $this->assertSame('Boulevard Centro, Tegucigalpa', $tenant->address);
        $this->assertSame('Hola, quiero información de {business_name}.', $tenant->whatsapp_message);
    }

    public function test_user_without_settings_update_permission_cannot_save_business_settings(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false, slug: 'cafe-norte-settings-viewer');

        $originalName = $tenant->name;
        $originalPhone = $tenant->whatsapp_number;
        $originalAddress = $tenant->address;
        $originalMessage = $tenant->whatsapp_message;

        app()->instance(TenantPermissionGuardInterface::class, new class implements TenantPermissionGuardInterface
        {
            public function allows(User $user, Tenant $tenant, Capability $capability, Permission $permission): bool
            {
                return $permission !== Permission::SETTINGS_UPDATE;
            }
        });

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(Settings::class)
            ->set('data.name', 'No Debe Persistir')
            ->set('data.whatsapp_number', '1111-2222')
            ->set('data.description', 'Intento sin permiso update.')
            ->set('data.address', 'Direccion no autorizada')
            ->set('data.whatsapp_message', 'Mensaje no autorizado')
            ->call('save')
            ->assertHasNoErrors();

        $tenant->refresh();

        $this->assertSame($originalName, $tenant->name);
        $this->assertSame($originalPhone, $tenant->whatsapp_number);
        $this->assertSame($originalAddress, $tenant->address);
        $this->assertSame($originalMessage, $tenant->whatsapp_message);
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

    public function test_pro_owner_can_manage_menu_sections_from_marketing_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: true,
            slug: 'cafe-norte-menu-sections',
            businessType: 'food',
        );

        $firstSection = $this->makeMenuSection($tenant, 'Entradas', sortOrder: 1);
        $secondSection = $this->makeMenuSection($tenant, 'Bebidas', sortOrder: 2);

        $product = Product::factory()
            ->forTenant($tenant)
            ->create([
                'menu_section_id' => $secondSection->getKey(),
                'name' => 'Limonada frozen',
            ]);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        $component = Livewire::test(ManageMarketing::class);

        $component
            ->callTableAction('create', null, [
                'name' => 'Promociones',
                'is_active' => true,
            ])
            ->assertHasNoErrors();

        $promotions = MenuSection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('name', 'Promociones')
            ->firstOrFail();

        self::assertSame(3, (int) $promotions->sort_order);

        $component
            ->callTableAction('edit', $promotions, [
                'name' => 'Promociones activas',
                'is_active' => false,
            ])
            ->assertHasNoErrors()
            ->call('updateTableColumnState', 'name', (string) $firstSection->getKey(), 'Especialidades')
            ->assertHasNoErrors()
            ->call('updateTableColumnState', 'is_active', (string) $firstSection->getKey(), false)
            ->assertHasNoErrors()
            ->call('reorderTable', [
                (string) $promotions->getKey(),
                (string) $firstSection->getKey(),
                (string) $secondSection->getKey(),
            ])
            ->callTableAction('delete', $secondSection->fresh())
            ->assertHasNoErrors();

        $firstSection->refresh();
        $promotions->refresh();
        $product->refresh();

        self::assertSame('Especialidades', $firstSection->name);
        self::assertFalse((bool) $firstSection->is_active);
        self::assertSame('Promociones activas', $promotions->name);
        self::assertFalse((bool) $promotions->is_active);
        self::assertSame(1, (int) $promotions->sort_order);
        self::assertSame(2, (int) $firstSection->sort_order);
        self::assertNull($secondSection->fresh());
        self::assertNull($product->menu_section_id);
    }

    public function test_pro_owner_can_save_public_business_profile_from_filament_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(
            isPro: true,
            slug: 'cafe-norte-profile',
            tenantState: [
                'name' => 'Cafe Norte',
            ],
        );

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(EditTenantProfile::class)
            ->set('data.name', 'Cafe Norte Signature')
            ->set('data.slogan', 'Cafe y brunch todo el dia')
            ->set('data.description', 'Brunch, cafe de especialidad y pan artesanal.')
            ->set('data.about', 'Atendemos desayunos y reuniones casuales.')
            ->set('data.email', 'hola@cafenorte.test')
            ->set('data.phone', '2233-4455')
            ->set('data.whatsapp_number', '9988-7766')
            ->set('data.whatsapp_message', 'Hola, quiero reservar una mesa.')
            ->set('data.address', 'Colonia Palmira, Tegucigalpa')
            ->set('data.brand_color', '#1D4ED8')
            ->set('data.primary_color', '#0F172A')
            ->set('data.hours_data', [
                [
                    'day' => 'lunes',
                    'open' => '08:00',
                    'close' => '17:00',
                    'is_closed' => false,
                ],
                [
                    'day' => 'martes',
                    'open' => '09:00',
                    'close' => '18:00',
                    'is_closed' => false,
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $tenant->refresh();

        $this->assertSame('Cafe Norte Signature', $tenant->name);
        $this->assertSame('Cafe y brunch todo el dia', $tenant->slogan);
        $this->assertSame('hola@cafenorte.test', $tenant->email);
        $this->assertSame('9988-7766', $tenant->whatsapp_number);
        $this->assertSame('#1D4ED8', $tenant->brand_color);
        $this->assertNull($tenant->simple_schedule);

        $hours = OpeningHour::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->orderBy('day_of_week')
            ->get(['day_of_week', 'opening_time', 'closing_time'])
            ->map(static fn (OpeningHour $openingHour): array => [
                'day_of_week' => $openingHour->day_of_week,
                'opening_time' => $openingHour->getRawOpeningTime(),
                'closing_time' => $openingHour->getRawClosingTime(),
            ])
            ->all();

        $this->assertSame([
            [
                'day_of_week' => 1,
                'opening_time' => '08:00:00',
                'closing_time' => '17:00:00',
            ],
            [
                'day_of_week' => 2,
                'opening_time' => '09:00:00',
                'closing_time' => '18:00:00',
            ],
        ], $hours);
    }

    public function test_pro_owner_can_download_qr_poster_from_marketing_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: true, slug: 'cafe-norte-poster');

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(ManageMarketing::class)
            ->call('downloadPoster')
            ->assertFileDownloaded($tenant->slug.'-cartel-qr.pdf');
    }

    public function test_free_owner_can_submit_payment_proof_and_store_pending_payment(): void
    {
        Storage::fake('local');

        [$user, $tenant] = $this->makeTenantOwner(isPro: false, slug: 'cafe-norte-proof');

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(ManageBilling::class)
            ->set('data.bank_reference', 'TRX-998877')
            ->set('data.proof_image_path', UploadedFile::fake()->image('proof.png'))
            ->call('submitPayment')
            ->assertHasNoErrors();

        $payment = SubscriptionPayment::query()
            ->where('tenant_id', $tenant->getKey())
            ->firstOrFail();

        $this->assertSame('TRX-998877', $payment->reference_number);
        $this->assertSame(SubscriptionPayment::STATUS_PENDING, $payment->status);
        $this->assertTrue(Storage::disk('local')->exists($payment->proof_path));
    }

    public function test_user_without_billing_manage_permission_cannot_submit_payment_proof(): void
    {
        Storage::fake('local');

        [$user, $tenant] = $this->makeTenantOwner(isPro: false, slug: 'cafe-norte-proof-viewer');

        app()->instance(TenantPermissionGuardInterface::class, new class implements TenantPermissionGuardInterface
        {
            public function allows(User $user, Tenant $tenant, Capability $capability, Permission $permission): bool
            {
                return $permission !== Permission::BILLING_MANAGE;
            }
        });

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(ManageBilling::class)
            ->set('data.bank_reference', 'TRX-NO-MANAGE')
            ->set('data.proof_image_path', UploadedFile::fake()->image('proof-no-manage.png'))
            ->call('submitPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('subscription_payments', [
            'tenant_id' => $tenant->getKey(),
            'reference_number' => 'TRX-NO-MANAGE',
        ]);
    }

    public function test_pro_owner_can_download_receipt_from_billing_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: true, slug: 'cafe-norte-receipt');

        $payment = SubscriptionPayment::forceCreate([
            'tenant_id' => $tenant->getKey(),
            'amount' => 599.00,
            'bank' => 'bank_transfer',
            'reference_number' => 'REC-4455',
            'proof_path' => 'payments/proofs/receipt-proof.png',
            'status' => SubscriptionPayment::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(ManageBilling::class)
            ->call('downloadReceipt', $payment)
            ->assertFileDownloaded('recibo-'.$payment->id.'.pdf');
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $tenantState
     * @param  array<string, mixed>  $categoryState
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(
        bool $isPro,
        string $slug,
        string $businessType = 'retail',
        array $tenantState = [],
        array $categoryState = [],
    ): array {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create($categoryState);

        $tenantFactory = Tenant::factory()->state(array_merge([
            'user_id' => $user->id,
            'slug' => $slug,
            'business_type' => $businessType,
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ], $tenantState));

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
