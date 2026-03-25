<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\App\Pages\GrowthCenter;
use App\Filament\App\Pages\ManageBilling;
use App\Filament\App\Pages\ManageMarketing;
use App\Filament\App\Pages\Settings;
use App\Models\Appointment;
use App\Filament\App\Resources\AppointmentResource;
use App\Filament\App\Resources\CustomerProfileResource;
use App\Filament\App\Resources\CustomerTouchpointResource;
use App\Filament\App\Resources\MenuSectionResource;
use App\Filament\App\Resources\OperationalPlaybookTaskResource;
use App\Filament\App\Resources\ServiceResource;
use App\Filament\App\Resources\TenantUserResource;
use App\Models\Category;
use App\Models\City;
use App\Models\Follow;
use App\Models\Lead;
use App\Models\LoyaltyProgram;
use App\Models\OperationalPlaybookTask;
use App\Models\Order;
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
        $response->assertSee('Tienda y catálogo');
        $response->assertSee('Ruta de lanzamiento para retail');
        $response->assertSee('Playbook operativo del retail');
        $response->assertSee('Checklist inicial');
        $response->assertSee('Workspace inicial');
        $response->assertSee('Mesa operativa con SLA');
        $response->assertSee('Madurez operativa');
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
            ->assertSee('Ruta de lanzamiento para servicios')
            ->assertSee('Catálogo de servicios')
            ->assertSee('Agenda de citas')
            ->assertSee('Playbook operativo de servicios');

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
            ->assertSee('Clinica dental')
            ->assertSee('Ruta de lanzamiento para odontologia')
            ->assertSee('Servicios clinicos')
            ->assertSee('Playbook de clinica dental')
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
            ->assertSee('Ruta de lanzamiento para comida')
            ->assertSee('Playbook operativo del restaurante');

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
            ->assertSee('Tienda de celulares premium')
            ->assertSee('Ruta de lanzamiento para celulares premium')
            ->assertSee('Catalogo de equipos')
            ->assertSee('Playbook de tienda premium')
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
            ->assertSee('Ir a suscripción');

        $this->get(route('filament.app.resources.followers.index', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('La lista de seguidores se desbloquea con PRO');

        $this->get(route('filament.app.resources.leads.index', ['tenant' => $tenant->slug], false))
            ->assertOk()
            ->assertSee('Todavía no tienes prospectos');
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
            ->assertSee('CRM y upsell para celulares premium')
            ->assertSee('Abrir clientes')
            ->assertSee('Ver contactos')
            ->assertSee('Pipeline y conversion')
            ->assertSee('Automatizaciones externas')
            ->assertSee('Analitica PYME profunda')
            ->assertSee('Oferta top')
            ->assertSee('Recurrencia')
            ->assertSee('Entrega de email')
            ->assertSee('Seguimiento de cotizaciones')
            ->assertSee('Fidelidad para clientes frecuentes')
            ->assertSee('L. 840.00')
            ->assertSee('Clientes que vuelven');

        $this->get(CustomerProfileResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('CRM de clientes')
            ->assertSee('Ana');

        $this->get(CustomerTouchpointResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('Historial de contactos');
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
            ->assertSee('CRM y continuidad para clinica dental')
            ->assertSee('WhatsApp de seguimiento clinico')
            ->assertSee('Recordatorio pre-cita')
            ->assertSee('Pacientes para control e higiene')
            ->assertSee('Agenda lista')
            ->assertSee('Oferta top')
            ->assertSee('Cobertura de opt-in');
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

    public function test_pro_owner_can_download_qr_poster_from_marketing_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: true, slug: 'cafe-norte-poster');

        $this->actingAs($user);
        $this->setAppPanelTenantContext($tenant);

        Livewire::test(ManageMarketing::class)
            ->call('downloadPoster')
            ->assertFileDownloaded($tenant->slug . '-cartel-qr.pdf');
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
        $this->assertNotNull($payment->proof_path);
        $this->assertTrue(Storage::disk('local')->exists($payment->proof_path));
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
            ->assertFileDownloaded('recibo-' . $payment->id . '.pdf');
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
}
