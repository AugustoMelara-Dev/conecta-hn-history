<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\TenantGovernance\Crm\CrmDefaultPipelineService;
use App\Domain\Access\Enums\Role;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Filament\App\Resources\CrmDealResource;
use App\Filament\App\Resources\CrmPipelineResource;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class CrmDealResourceGatingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_free_tenant_cannot_see_or_open_deals_resource(): void
    {
        [$owner, $tenant] = $this->tenantUser(Role::OWNER, TenantPlan::SEMILLA->value);

        $this->setAppPanelTenantContext($owner, $tenant);

        self::assertFalse(CrmDealResource::shouldRegisterNavigation());
        self::assertFalse(CrmDealResource::canViewAny());
        self::assertFalse(CrmPipelineResource::shouldRegisterNavigation());
        self::assertFalse(CrmPipelineResource::canViewAny());

        $this->get(CrmDealResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertForbidden();

        $this->get(CrmPipelineResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_viewer_has_read_only_deal_access(): void
    {
        [$viewer, $tenant] = $this->tenantUser(Role::VIEWER);
        $deal = $this->deal($tenant);

        $this->setAppPanelTenantContext($viewer, $tenant);

        self::assertTrue(CrmDealResource::canViewAny());
        self::assertFalse(CrmDealResource::canCreate());
        self::assertFalse(CrmDealResource::canEdit($deal));
        self::assertFalse(CrmDealResource::canMoveStage());
        self::assertFalse(CrmDealResource::canDelete($deal));
        self::assertTrue(CrmPipelineResource::canViewAny());
        self::assertFalse(CrmPipelineResource::canCreate());
        self::assertFalse(CrmPipelineResource::canEdit($deal->pipeline));
        self::assertFalse(CrmPipelineResource::canDelete($deal->pipeline));

        $this->get(CrmDealResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('Oportunidad de prueba');

        $this->get(CrmPipelineResource::getUrl('index', ['tenant' => $tenant->slug]))
            ->assertOk()
            ->assertSee('Pipeline comercial');

        $this->get(CrmDealResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $deal]))
            ->assertForbidden();

        $this->get(CrmPipelineResource::getUrl('create', ['tenant' => $tenant->slug]))
            ->assertForbidden();
    }

    public function test_manager_can_create_and_edit_but_cannot_delete(): void
    {
        [$manager, $tenant] = $this->tenantUser(Role::MANAGER);
        $deal = $this->deal($tenant);

        $this->setAppPanelTenantContext($manager, $tenant);

        self::assertTrue(CrmDealResource::canViewAny());
        self::assertTrue(CrmDealResource::canCreate());
        self::assertTrue(CrmDealResource::canEdit($deal));
        self::assertTrue(CrmDealResource::canMoveStage());
        self::assertFalse(CrmDealResource::canDelete($deal));
        self::assertTrue(CrmPipelineResource::canCreate());
        self::assertTrue(CrmPipelineResource::canEdit($deal->pipeline));
        self::assertFalse(CrmPipelineResource::canDelete($deal->pipeline));

        $this->get(CrmDealResource::getUrl('create', ['tenant' => $tenant->slug]))
            ->assertOk();

        $this->get(CrmDealResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $deal]))
            ->assertOk();

        $this->get(CrmPipelineResource::getUrl('create', ['tenant' => $tenant->slug]))
            ->assertOk();

        $this->get(CrmPipelineResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $deal->pipeline]))
            ->assertOk();
    }

    public function test_admin_can_delete_deals(): void
    {
        [$admin, $tenant] = $this->tenantUser(Role::ADMIN);
        $deal = $this->deal($tenant);

        $this->setAppPanelTenantContext($admin, $tenant);

        self::assertTrue(CrmDealResource::canDelete($deal));
        self::assertTrue(CrmDealResource::canDeleteAny());

        $emptyPipeline = CrmPipeline::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Pipeline temporal',
            'key' => 'pipeline-temporal',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 50,
        ]);

        self::assertFalse(CrmPipelineResource::canDelete($deal->pipeline));
        self::assertTrue(CrmPipelineResource::canDelete($emptyPipeline));
    }

    public function test_pipeline_resource_normalizes_default_pipeline_and_stage(): void
    {
        [$admin, $tenant] = $this->tenantUser(Role::ADMIN);
        $deal = $this->deal($tenant);
        $newPipeline = CrmPipeline::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Nuevo default',
            'key' => 'nuevo-default',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        $this->setAppPanelTenantContext($admin, $tenant);

        CrmPipelineResource::normalizeDefaultPipeline($newPipeline);

        self::assertFalse((bool) $deal->pipeline->refresh()->is_default);
        self::assertTrue((bool) $newPipeline->refresh()->is_default);

        $firstStage = CrmStage::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $newPipeline->getKey(),
            'name' => 'Discovery',
            'key' => 'discovery',
            'type' => CrmStage::TYPE_OPEN,
            'is_default' => true,
        ]);
        $secondStage = CrmStage::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $newPipeline->getKey(),
            'name' => 'Propuesta',
            'key' => 'propuesta',
            'type' => CrmStage::TYPE_OPEN,
            'is_default' => true,
        ]);

        CrmStage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('crm_pipeline_id', $secondStage->crm_pipeline_id)
            ->whereKeyNot($secondStage->getKey())
            ->update(['is_default' => false]);

        self::assertFalse((bool) $firstStage->refresh()->is_default);
        self::assertTrue((bool) $secondStage->refresh()->is_default);
    }

    public function test_deal_notes_and_tasks_are_scoped_to_deal_customer_and_tenant(): void
    {
        [$manager, $tenant] = $this->tenantUser(Role::MANAGER);
        $deal = $this->deal($tenant);

        $this->setAppPanelTenantContext($manager, $tenant);

        $note = CustomerNote::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'customer_profile_id' => $deal->customer_profile_id,
            'crm_deal_id' => $deal->getKey(),
            'created_by_user_id' => $manager->getKey(),
            'note_type' => 'follow_up',
            'priority' => 'normal',
            'body' => 'Seguimiento desde oportunidad.',
        ]);

        $task = CustomerTask::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'customer_profile_id' => $deal->customer_profile_id,
            'crm_deal_id' => $deal->getKey(),
            'created_by_user_id' => $manager->getKey(),
            'task_type' => 'follow_up',
            'title' => 'Llamar cliente',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        self::assertTrue($deal->notes()->whereKey($note->getKey())->exists());
        self::assertTrue($deal->tasks()->whereKey($task->getKey())->exists());
        self::assertSame((string) $tenant->getKey(), (string) $note->tenant_id);
        self::assertSame((int) $deal->customer_profile_id, (int) $note->customer_profile_id);
        self::assertSame((int) $deal->getKey(), (int) $note->crm_deal_id);
        self::assertSame((int) $deal->getKey(), (int) $task->crm_deal_id);
    }

    public function test_crm_mutations_emit_minimal_audit_logs(): void
    {
        config(['activitylog.enabled' => true]);

        [$manager, $tenant] = $this->tenantUser(Role::MANAGER);
        $deal = $this->deal($tenant);
        $proposal = $deal->pipeline->stages()->where('key', 'proposal')->firstOrFail();

        $deal->forceFill([
            'crm_stage_id' => $proposal->getKey(),
            'value' => 13500,
            'status' => CrmDeal::STATUS_OPEN,
        ])->save();

        CustomerNote::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'customer_profile_id' => $deal->customer_profile_id,
            'crm_deal_id' => $deal->getKey(),
            'created_by_user_id' => $manager->getKey(),
            'note_type' => 'follow_up',
            'priority' => 'normal',
            'body' => 'Audit note.',
        ]);

        CustomerTask::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'customer_profile_id' => $deal->customer_profile_id,
            'crm_deal_id' => $deal->getKey(),
            'created_by_user_id' => $manager->getKey(),
            'task_type' => 'follow_up',
            'title' => 'Audit task',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $deal->delete();

        self::assertGreaterThanOrEqual(2, Activity::query()->where('log_name', 'crm_deals')->count());
        self::assertSame(1, Activity::query()->where('log_name', 'crm_notes')->count());
        self::assertSame(1, Activity::query()->where('log_name', 'crm_tasks')->count());
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
    private function tenantUser(Role $role, string $plan = TenantPlan::PRO->value): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()
            ->forUser($owner)
            ->create([
                'plan_type' => $plan,
                'subscription_status' => 'active',
                'business_type' => 'retail',
            ]);

        $user = $role === Role::OWNER
            ? $owner
            : User::factory()->create(['tenant_id' => (string) $tenant->getKey()]);

        $tenant->memberships()->create([
            'user_id' => $user->getKey(),
            'role' => $role,
            'accepted_at' => now(),
        ]);

        if ($role === Role::OWNER) {
            $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();
        }

        return [$user, $tenant];
    }

    private function deal(Tenant $tenant): CrmDeal
    {
        $pipeline = app(CrmDefaultPipelineService::class)->ensureForTenant($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();
        $customer = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Cliente de prueba',
            'email' => 'cliente-crm@example.com',
            'preferred_channel' => 'whatsapp',
            'lifecycle_stage' => CustomerProfile::LIFECYCLE_PROSPECT,
            'customer_segment' => CustomerProfile::SEGMENT_PROSPECT,
        ]);

        return CrmDeal::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'customer_profile_id' => $customer->getKey(),
            'title' => 'Oportunidad de prueba',
            'value' => 12500,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_OPEN,
        ]);
    }

    private function setAppPanelTenantContext(User $user, Tenant $tenant): void
    {
        $this->actingAs($user);

        if (method_exists($this, 'actingAsTenant')) {
            $this->actingAsTenant((string) $tenant->getKey());
        }

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($tenant, isQuiet: true);
    }
}
