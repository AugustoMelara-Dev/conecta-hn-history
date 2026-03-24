<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Filament\Pages\ManageGeneralSettings;
use App\Models\City;
use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->assertSee('data-panel-theme-link="admin-panel"', false)
            ->assertSee('data-filament-critical-theme="true"', false)
            ->assertDontSee('__filament_runtime', false)
            ->assertSee('Torre de control multi-negocio')
            ->assertSee('Colas globales por impacto')
            ->assertSee('SLA por equipo')
            ->assertSee('Riesgo de churn')
            ->assertSee('Vista por owner')
            ->assertSee('Vista por equipo')
            ->assertSee('Cartera vencida por SLA')
            ->assertSee('Embudo comercial entre tenants')
            ->assertSee('Cohortes de activacion')
            ->assertSee('Metas por owner')
            ->assertSee('Metas por equipo')
            ->assertSee('Metas mensuales por vertical')
            ->assertSee('Cohortes por plan')
            ->assertSee('Cohortes por ciudad')
            ->assertSee('Cohortes por canal')
            ->assertSee('Cohortes por owner')
            ->assertSee('Expansion por segmento')
            ->assertSee('Churn por vertical')
            ->assertSee('Playbooks revenue ops')
            ->assertSee('Mesa operativa')
            ->assertSee('Seguimiento comercial entre tenants');

        $this->get(route('filament.admin.pages.manage-general-settings', [], false))
            ->assertOk()
            ->assertSee('Control de disponibilidad')
            ->assertSee('Modo mantenimiento');

        $this->get(route('filament.admin.pages.billing', [], false))
            ->assertOk()
            ->assertSee('Pagos, precio PRO y soporte')
            ->assertSee('Embudo comercial')
            ->assertSee('Negocios PRO');

        $this->get(route('filament.admin.resources.operational-playbook-tasks.index', [], false))
            ->assertOk()
            ->assertSee('Mesa operativa')
            ->assertSee('Owner interno')
            ->assertSee('Seguimiento');

        $this->get(route('filament.admin.resources.tenants.index', [], false))
            ->assertOk()
            ->assertSee('Estado comercial')
            ->assertSee('Owner interno')
            ->assertSee('CRM interno');
    }

    public function test_superadmin_dashboard_renders_live_cross_tenant_workboard_data(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
            'email' => 'owner-tower@example.com',
        ]);
        $internalOwner = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'name' => 'Andrea Seguimiento',
            'email' => 'andrea.seguimiento@example.com',
        ]);
        $assignee = User::factory()->create([
            'role' => UserRole::STAFF,
            'name' => 'Daniel Operacion Negocio',
            'email' => 'daniel.negocio@example.com',
        ]);
        $city = City::factory()->create([
            'name' => 'Tegucigalpa',
            'slug' => 'tegucigalpa',
        ]);

        $tenant = Tenant::factory()->pro()->create([
            'user_id' => $owner->getKey(),
            'name' => 'Flagship Mobile Tower',
            'slug' => 'flagship-mobile-tower',
            'city_id' => $city->getKey(),
            'whatsapp_number' => '99998888',
            'phone' => null,
            'website_url' => null,
            'subscription_ends_at' => now()->addDays(2),
        ]);

        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        OperationalPlaybookTask::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'assigned_user_id' => (string) $assignee->getKey(),
            'admin_owner_id' => (string) $internalOwner->getKey(),
            'trigger_key' => 'lead_follow_up',
            'queue' => 'leads',
            'route_key' => 'leads',
            'segment_key' => 'premium_phone_store',
            'suggested_preset_key' => 'phone_sales_advisor',
            'priority' => OperationalPlaybookTask::PRIORITY_HIGH,
            'status' => OperationalPlaybookTask::STATUS_OPEN,
            'follow_up_status' => OperationalPlaybookTask::FOLLOW_UP_SCHEDULED,
            'sla_minutes' => 30,
            'title' => 'Responder cotizacion premium',
            'summary' => 'Lead de alto ticket esperando contacto comercial.',
            'playbook_steps' => ['Contactar al lead hoy mismo.'],
            'due_at' => now()->addMinutes(15),
            'next_follow_up_at' => now()->addMinutes(45),
            'internal_notes' => '24/03/2026 08:00 - Andrea Seguimiento: Confirmar disponibilidad del equipo premium.',
        ]);

        SubscriptionPayment::query()
            ->withoutGlobalScopes()
            ->forceCreate([
                'tenant_id' => (string) $tenant->getKey(),
                'amount' => 500.00,
                'bank' => 'BAC',
                'reference_number' => 'TOWER-20260324',
                'proof_path' => 'proofs/tower-20260324.jpg',
                'status' => SubscriptionPayment::STATUS_APPROVED,
                'reviewed_by' => (string) $superAdmin->getKey(),
                'reviewed_at' => now()->subHour(),
                'subscription_extended_at' => now()->subHour(),
                'plan_before_upgrade' => 'crecimiento',
            ]);

        $this->actingAs($superAdmin);

        $this->get(route('filament.admin.pages.dashboard', [], false))
            ->assertOk()
            ->assertSee('Flagship Mobile Tower')
            ->assertSee('Responder cotizacion premium')
            ->assertSee('Andrea Seguimiento')
            ->assertSee('Daniel Operacion Negocio')
            ->assertSee('Vista por owner')
            ->assertSee('Riesgo de churn')
            ->assertSee('Metas mensuales por vertical')
            ->assertSee('Cohortes por plan')
            ->assertSee('Cohortes por owner')
            ->assertSee('Tegucigalpa')
            ->assertSee('WhatsApp directo')
            ->assertSee('PRO este mes')
            ->assertSee('Tienda de celulares premium');
    }

    public function test_superadmin_can_save_general_settings_from_filament_page(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ManageGeneralSettings::class)
            ->set('data.pro_plan_price', 599.00)
            ->set('data.grace_period_days', 5)
            ->set('data.free_plan_product_limit', 15)
            ->set('data.pro_plan_product_limit', 300)
            ->set('data.bank_transfer_instructions', '<p>Transfiere {precio} y envia el comprobante.</p>')
            ->set('data.support_whatsapp_number', '99998888')
            ->set('data.support_email', 'soporte@conectahn.test')
            ->set('data.maintenance_mode', true)
            ->set('data.allow_registrations', false)
            ->call('save')
            ->assertHasNoErrors();

        $settings = new GeneralSettings;

        $this->assertSame(599.0, $settings->pro_plan_price);
        $this->assertSame(5, $settings->grace_period_days);
        $this->assertSame(15, $settings->free_plan_product_limit);
        $this->assertSame(300, $settings->pro_plan_product_limit);
        $this->assertSame('<p>Transfiere {precio} y envia el comprobante.</p>', $settings->bank_transfer_instructions);
        $this->assertSame('99998888', $settings->support_whatsapp_number);
        $this->assertSame('soporte@conectahn.test', $settings->support_email);
        $this->assertTrue($settings->maintenance_mode);
        $this->assertFalse($settings->allow_registrations);
    }

    public function test_tenant_edit_page_renders_internal_timeline_tab(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin);

        $tenant = Tenant::query()->firstOrFail();

        $this->get(route('filament.admin.resources.tenants.edit', ['record' => $tenant], false))
            ->assertOk()
            ->assertSee('Timeline interno');
    }

    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);
        Filament::setCurrentPanel(null);

        parent::tearDown();
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
            'filament.admin.resources.operational-playbook-tasks.index' => [],
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
