<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\TenantCrmPortfolioManager;
use App\Models\OperationalPlaybookTask;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class TenantCrmPortfolioManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_persists_watchlist_and_opens_renewal_attention_for_expiring_pro_accounts(): void
    {
        [$owner, $tenant] = $this->makeTenantOwner([
            'name' => 'Bufete Premium Tegucigalpa',
            'slug' => 'bufete-premium-tegucigalpa',
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addDays(2),
            'crm_sla_hours' => 12,
            'crm_status' => Tenant::CRM_STATUS_ACTIVE,
        ]);

        app(TenantCrmPortfolioManager::class)->syncSignals();

        $tenant->refresh();

        $this->assertGreaterThanOrEqual(90, (int) $tenant->crm_watch_score);
        $this->assertSame('automation', $tenant->crm_watch_source);
        $this->assertNotNull($tenant->crm_watch_flagged_at);
        $this->assertStringContainsString('plan PRO', (string) $tenant->crm_watch_reason);
        $this->assertStringContainsString('Vence en', (string) $tenant->crm_watch_metric);
        $this->assertNotNull($tenant->crm_next_follow_up_at);
        $this->assertTrue($tenant->crm_next_follow_up_at->isFuture());
        $this->assertSame(Tenant::CRM_TEAM_BILLING, (string) $tenant->getRawOriginal('crm_team'));

        $task = $this->findPlaybookTask($tenant, 'renewal_attention');

        $this->assertSame('billing', $task->queue);
        $this->assertSame(OperationalPlaybookTask::STATUS_OPEN, $task->status);
        $this->assertSame((string) $owner->getKey(), (string) $task->assigned_user_id);
    }

    public function test_it_clears_watchlist_and_closes_managed_task_when_account_recovers(): void
    {
        [, $tenant] = $this->makeTenantOwner([
            'name' => 'Ferreteria Centro',
            'slug' => 'ferreteria-centro',
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'crm_status' => Tenant::CRM_STATUS_ACTIVE,
            'crm_watch_score' => 88,
            'crm_watch_tone' => 'warning',
            'crm_watch_source' => 'automation',
            'crm_watch_metric' => 'Sin actividad en 30+ dias',
            'crm_watch_reason' => 'La cuenta estaba en observacion.',
            'crm_watch_flagged_at' => now()->subDay(),
        ]);

        Product::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Taladro industrial',
            'price' => 4200,
            'is_visible' => true,
        ]);

        $task = OperationalPlaybookTask::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'trigger_key' => 'tenant_churn_watch',
            'queue' => 'retention',
            'route_key' => 'dashboard',
            'segment_key' => 'hardware_store',
            'priority' => OperationalPlaybookTask::PRIORITY_HIGH,
            'status' => OperationalPlaybookTask::STATUS_OPEN,
            'follow_up_status' => OperationalPlaybookTask::FOLLOW_UP_ASSIGNED,
            'sla_minutes' => 720,
            'title' => 'Proteger cuenta en riesgo',
            'summary' => 'Seguimiento preventivo de cartera.',
            'playbook_steps' => ['Contactar al negocio.'],
            'due_at' => now()->addHours(8),
        ]);

        app(TenantCrmPortfolioManager::class)->syncSignals();

        $tenant->refresh();
        $task->refresh();

        $this->assertSame(0, (int) $tenant->crm_watch_score);
        $this->assertNull($tenant->crm_watch_reason);
        $this->assertNull($tenant->crm_watch_metric);
        $this->assertNull($tenant->crm_watch_flagged_at);
        $this->assertSame(OperationalPlaybookTask::STATUS_COMPLETED, $task->status);
        $this->assertSame(OperationalPlaybookTask::FOLLOW_UP_CLOSED, $task->follow_up_status);
        $this->assertNull($task->next_follow_up_at);
        $this->assertNotNull($task->completed_at);
    }

    public function test_artisan_command_syncs_portfolio_signals(): void
    {
        [, $tenant] = $this->makeTenantOwner([
            'name' => 'Clinica Centro Medico',
            'slug' => 'clinica-centro-medico',
            'plan_type' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addDays(3),
            'crm_status' => Tenant::CRM_STATUS_ACTIVE,
        ]);

        $exitCode = Artisan::call('crm:sync-portfolio');

        $tenant->refresh();

        $this->assertSame(0, $exitCode);
        $this->assertGreaterThan(0, (int) $tenant->crm_watch_score);
        $this->assertSame('automation', $tenant->crm_watch_source);
    }

    public function test_it_persists_business_segment_key_and_opens_activation_follow_up_for_new_accounts_without_offer(): void
    {
        [$owner, $tenant] = $this->makeTenantOwner([
            'name' => 'Clinica Dental Sonrisa Norte',
            'slug' => 'clinica-dental-sonrisa-norte',
            'business_type' => 'service',
            'crm_status' => Tenant::CRM_STATUS_NEW,
            'crm_sla_hours' => 10,
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(1),
        ]);

        app(TenantCrmPortfolioManager::class)->syncSignals();

        $tenant->refresh();

        $this->assertSame('dental_clinic', $tenant->business_segment_key);
        $this->assertSame(Tenant::CRM_TEAM_ACTIVATION, (string) $tenant->getRawOriginal('crm_team'));
        $this->assertSame(Tenant::CRM_PRIORITY_HIGH, (string) $tenant->getRawOriginal('crm_priority'));
        $this->assertNotNull($tenant->crm_next_follow_up_at);
        $this->assertStringContainsString('Sin catalogo ni servicios', (string) $tenant->crm_watch_metric);

        $task = $this->findPlaybookTask($tenant, 'activation_follow_up');

        $this->assertSame('activation', $task->queue);
        $this->assertSame(OperationalPlaybookTask::STATUS_OPEN, $task->status);
        $this->assertSame((string) $owner->getKey(), (string) $task->assigned_user_id);
        $this->assertSame('Completar activacion de clinica dental', (string) $task->title);
        $this->assertStringContainsString('valoracion, agenda y recepcion', (string) $task->summary);
    }

    public function test_it_opens_expansion_opportunity_for_healthy_non_pro_accounts_ready_for_growth(): void
    {
        $internalOwner = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);

        [$owner, $tenant] = $this->makeTenantOwner([
            'name' => 'Flagship Mobile Premium',
            'slug' => 'flagship-mobile-premium',
            'business_type' => 'retail',
            'plan_type' => 'semilla',
            'subscription_status' => 'active',
            'crm_status' => Tenant::CRM_STATUS_ACTIVE,
            'crm_owner_id' => $internalOwner->getKey(),
            'crm_sla_hours' => 18,
            'whatsapp_number' => '99998888',
            'created_at' => now()->subDays(14),
            'updated_at' => now()->subDay(),
        ]);

        Product::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'iPhone Flagship Pro',
            'price' => 35999,
            'is_visible' => true,
        ]);

        app(TenantCrmPortfolioManager::class)->syncSignals();

        $tenant->refresh();

        $this->assertSame('premium_phone_store', $tenant->business_segment_key);
        $this->assertSame(Tenant::CRM_TEAM_GROWTH, (string) $tenant->getRawOriginal('crm_team'));
        $this->assertSame(Tenant::CRM_PRIORITY_HIGH, (string) $tenant->getRawOriginal('crm_priority'));

        $task = $this->findPlaybookTask($tenant, 'expansion_opportunity');

        $this->assertSame('growth', $task->queue);
        $this->assertSame(OperationalPlaybookTask::STATUS_OPEN, $task->status);
        $this->assertSame((string) $owner->getKey(), (string) $task->assigned_user_id);
        $this->assertSame((string) $internalOwner->getKey(), (string) $task->admin_owner_id);
        $this->assertSame('Abrir expansion flagship y bundles', (string) $task->title);
        $this->assertStringContainsString('upsell de accesorios, trade-in, aftercare', (string) $task->summary);
    }

    public function test_it_applies_vertical_specific_growth_playbooks_for_strategic_segments(): void
    {
        $cases = [
            [
                'segment' => 'corporate_law_firm',
                'name' => 'Bufete Corporativo Atlas',
                'slug' => 'bufete-corporativo-atlas',
                'business_type' => 'service',
                'service_name' => 'Discovery mercantil',
                'expected_title' => 'Abrir upsell legal corporativo',
                'expected_phrase' => 'discovery legal, seguimiento de retainer',
            ],
            [
                'segment' => 'financial_consultancy',
                'name' => 'Consultoria Financiera Vector CFO',
                'slug' => 'consultoria-financiera-vector-cfo',
                'business_type' => 'service',
                'service_name' => 'Diagnostico de flujo de caja',
                'expected_title' => 'Abrir expansion de consultoria financiera',
                'expected_phrase' => 'fractional CFO y seguimiento financiero de mayor ticket',
            ],
            [
                'segment' => 'hardware_store',
                'name' => 'Ferreteria Maestro Norte',
                'slug' => 'ferreteria-maestro-norte',
                'business_type' => 'retail',
                'product_name' => 'Taladro profesional',
                'expected_title' => 'Abrir crecimiento por proyectos y cobertura',
                'expected_phrase' => 'cuentas por proyecto, cobertura extendida',
            ],
        ];

        foreach ($cases as $case) {
            [$owner, $tenant] = $this->makeTenantOwner([
                'name' => $case['name'],
                'slug' => $case['slug'],
                'business_type' => $case['business_type'],
                'plan_type' => 'semilla',
                'subscription_status' => 'active',
                'crm_status' => Tenant::CRM_STATUS_ACTIVE,
                'whatsapp_number' => '99997777',
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDay(),
            ]);

            if (isset($case['product_name'])) {
                Product::factory()->create([
                    'tenant_id' => (string) $tenant->getKey(),
                    'name' => $case['product_name'],
                    'price' => 4200,
                    'is_visible' => true,
                ]);
            }

            if (isset($case['service_name'])) {
                Service::factory()->create([
                    'tenant_id' => (string) $tenant->getKey(),
                    'name' => $case['service_name'],
                    'is_active' => true,
                ]);
            }

            app(TenantCrmPortfolioManager::class)->syncSignals();

            $tenant->refresh();

            $this->assertSame($case['segment'], $tenant->business_segment_key);

            $task = $this->findPlaybookTask($tenant, 'expansion_opportunity');

            $this->assertSame((string) $owner->getKey(), (string) $task->assigned_user_id);
            $this->assertSame($case['expected_title'], (string) $task->title);
            $this->assertStringContainsString($case['expected_phrase'], (string) $task->summary);
        }
    }

    /**
     * @param  array<string, mixed>  $tenantState
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(array $tenantState = []): array
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $tenant = Tenant::factory()->create(array_merge([
            'user_id' => $owner->getKey(),
            'business_type' => 'retail',
            'is_active' => true,
        ], $tenantState));

        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        return [$owner, $tenant];
    }

    private function findPlaybookTask(Tenant $tenant, string $triggerKey): OperationalPlaybookTask
    {
        return OperationalPlaybookTask::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('trigger_key', $triggerKey)
            ->latest('created_at')
            ->firstOrFail();
    }
}
