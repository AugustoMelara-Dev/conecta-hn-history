<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CrmPipelineApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_free_tenant_cannot_access_gated_crm_pipeline_api(): void
    {
        [$owner, $tenant] = $this->tenantOwner(plan: TenantPlan::SEMILLA->value);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/pipelines')
            ->assertForbidden();
    }

    public function test_pro_tenant_gets_default_pipeline_with_stages(): void
    {
        [$owner, $tenant] = $this->tenantOwner();

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/pipelines')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'default')
            ->assertJsonPath('data.0.stages.0.key', 'new');

        $this->assertDatabaseHas('crm_pipelines', [
            'tenant_id' => (string) $tenant->getKey(),
            'key' => 'default',
        ]);
    }

    public function test_tenant_can_create_update_and_move_a_deal_inside_its_pipeline(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();
        $proposal = $pipeline->stages()->where('key', 'proposal')->firstOrFail();
        $customer = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Cliente CRM',
            'email' => 'cliente@example.com',
            'phone' => '99999999',
            'preferred_channel' => 'whatsapp',
            'lifecycle_stage' => CustomerProfile::LIFECYCLE_PROSPECT,
            'customer_segment' => CustomerProfile::SEGMENT_PROSPECT,
        ]);
        $lead = Lead::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Lead CRM',
            'email' => 'lead@example.com',
            'status' => 'new',
        ]);

        Sanctum::actingAs($owner);

        $create = $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/crm/deals', [
                'pipeline_id' => $pipeline->getKey(),
                'stage_id' => $stage->getKey(),
                'customer_profile_id' => $customer->getKey(),
                'lead_id' => $lead->getKey(),
                'title' => 'Implementacion CRM',
                'value' => 15000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Implementacion CRM')
            ->assertJsonPath('data.status', CrmDeal::STATUS_OPEN);

        $dealId = (int) $create->json('data.id');

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->patchJson("/api/v1/crm/deals/{$dealId}", [
                'title' => 'Implementacion CRM actualizada',
                'value' => 18000,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Implementacion CRM actualizada');

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->patchJson("/api/v1/crm/deals/{$dealId}/stage", [
                'stage_id' => $proposal->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('data.stage_id', $proposal->getKey());

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->deleteJson("/api/v1/crm/deals/{$dealId}")
            ->assertOk()
            ->assertJsonPath('message', 'Deal eliminado.');

        $this->assertDatabaseMissing('crm_deals', [
            'id' => $dealId,
            'tenant_id' => (string) $tenant->getKey(),
        ]);
    }

    public function test_deal_payload_rejects_cross_tenant_stage(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        [, $otherTenant] = $this->tenantOwner(email: 'other-owner@example.com');
        $pipeline = $this->pipeline($tenant);
        $otherStage = $this->pipeline($otherTenant)->stages()->where('key', 'new')->firstOrFail();

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/crm/deals', [
                'pipeline_id' => $pipeline->getKey(),
                'stage_id' => $otherStage->getKey(),
                'title' => 'Cruce indebido',
            ])
            ->assertNotFound();
    }

    public function test_pipeline_summary_reports_real_counts_and_values(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();

        CrmDeal::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'title' => 'Deal abierto',
            'value' => 5000,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_OPEN,
            'expected_close_at' => now()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/pipeline-summary')
            ->assertOk()
            ->assertJsonPath('data.totals.open_count', 1)
            ->assertJsonPath('data.totals.open_value', '5000.00')
            ->assertJsonPath('data.totals.overdue_count', 1);
    }

    public function test_non_owner_viewer_has_read_only_crm_api_access(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $viewer = User::factory()->create(['email' => 'viewer-crm@example.com']);
        $viewer->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();
        $tenant->memberships()->create([
            'user_id' => $viewer->getKey(),
            'role' => Role::VIEWER,
            'accepted_at' => now(),
        ]);

        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();
        $deal = CrmDeal::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'title' => 'Deal solo lectura',
            'value' => 3000,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        Sanctum::actingAs($viewer);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/pipelines')
            ->assertOk();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/deals')
            ->assertOk();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/pipeline-summary')
            ->assertOk();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/crm/deals', [
                'pipeline_id' => $pipeline->getKey(),
                'stage_id' => $stage->getKey(),
                'title' => 'Bloqueado viewer',
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->patchJson("/api/v1/crm/deals/{$deal->getKey()}", ['title' => 'No debe editar'])
            ->assertForbidden();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->patchJson("/api/v1/crm/deals/{$deal->getKey()}/stage", ['stage_id' => $stage->getKey()])
            ->assertForbidden();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->deleteJson("/api/v1/crm/deals/{$deal->getKey()}")
            ->assertForbidden();
    }

    public function test_delete_returns_not_found_when_deal_does_not_exist_in_tenant_scope(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->deleteJson('/api/v1/crm/deals/999999')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_delete_requires_authentication(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();
        $deal = CrmDeal::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'title' => 'Deal sin auth',
            'value' => 2000,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->deleteJson("/api/v1/crm/deals/{$deal->getKey()}")
            ->assertUnauthorized();
    }

    public function test_delete_forbidden_contract_returns_standard_payload_for_non_owner_without_delete_permission(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();
        $deal = CrmDeal::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'title' => 'Deal forbidden contract',
            'value' => 1500,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $manager = User::factory()->create(['email' => 'manager-crm-delete@example.com']);
        $manager->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();
        $tenant->memberships()->create([
            'user_id' => $manager->getKey(),
            'role' => Role::MANAGER,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->deleteJson("/api/v1/crm/deals/{$deal->getKey()}")
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_delete_contract_does_not_emit_422_when_payload_is_sent_because_no_body_validation_applies(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();
        $deal = CrmDeal::query()->withoutGlobalScopes()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'title' => 'Deal delete payload contract',
            'value' => 2500,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->deleteJson("/api/v1/crm/deals/{$deal->getKey()}", [
                'unexpected_field' => 'ignored',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Deal eliminado.');
    }

    public function test_crm_routes_keep_api_throttle_middleware_for_429_contract(): void
    {
        foreach ([
            'api.crm.deals.index',
            'api.crm.deals.store',
            'api.crm.deals.update',
            'api.crm.deals.stage.update',
            'api.crm.deals.destroy',
            'api.crm.pipelines.index',
            'api.crm.pipeline-summary.show',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            self::assertNotNull($route, "Route {$routeName} must exist.");
            self::assertContains('throttle:api', $route->gatherMiddleware(), "Route {$routeName} must keep throttle:api middleware.");
        }
    }

    public function test_429_contract_returns_standard_payload_when_api_limiter_is_exceeded(): void
    {
        [$owner, $tenant] = $this->tenantOwner();

        RateLimiter::for('api', static function (): Limit {
            return Limit::perMinute(1)->by('crm-contract-429');
        });

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/pipelines')
            ->assertOk();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/pipelines')
            ->assertStatus(429)
            ->assertJsonStructure(['message']);
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function tenantOwner(string $plan = TenantPlan::PRO->value, string $email = 'owner@example.com'): array
    {
        $owner = User::factory()->tenantOwner()->create([
            'email' => $email,
        ]);

        $tenant = Tenant::factory()
            ->forUser($owner)
            ->create([
                'plan_type' => $plan,
                'subscription_status' => 'active',
                'business_type' => 'retail',
            ]);

        $owner->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();

        $tenant->memberships()->create([
            'user_id' => $owner->getKey(),
            'role' => Role::OWNER,
            'accepted_at' => now(),
        ]);

        return [$owner, $tenant];
    }

    private function pipeline(Tenant $tenant): CrmPipeline
    {
        return app(\App\Application\TenantGovernance\Crm\CrmDefaultPipelineService::class)->ensureForTenant($tenant);
    }
}
