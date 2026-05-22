<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CrmPipelineApiTest extends TestCase
{
    use RefreshDatabase;

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

        self::assertTrue($tenant->hasFeature(TenantFeature::MARKETING_SUITE));
        self::assertFalse($tenant->hasFeature(TenantFeature::CRM_PIPELINES));

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

    public function test_pro_tenant_is_forbidden_when_crm_related_features_do_not_resolve(): void
    {
        [$owner, $tenant] = $this->tenantOwner();

        config()->set('tenant-features.features.marketing_suite.all_capabilities', ['service_packages']);

        self::assertFalse($tenant->hasFeature(TenantFeature::MARKETING_SUITE));
        self::assertFalse($tenant->hasFeature(TenantFeature::CRM_PIPELINES));

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/pipelines')
            ->assertForbidden();
    }

    public function test_tenant_can_manage_pipelines_and_stages_inside_crm_gate(): void
    {
        [$owner, $tenant] = $this->tenantOwner();

        Sanctum::actingAs($owner);

        $pipelineId = (int) $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/crm/pipelines', [
                'name' => 'Ventas enterprise',
                'description' => 'Pipeline para oportunidades de alto valor.',
                'is_default' => true,
                'sort_order' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ventas enterprise')
            ->assertJsonPath('data.key', 'ventas-enterprise')
            ->json('data.id');

        $this->assertDatabaseHas('crm_pipelines', [
            'tenant_id' => (string) $tenant->getKey(),
            'id' => $pipelineId,
            'key' => 'ventas-enterprise',
            'is_default' => true,
        ]);

        $stageId = (int) $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson("/api/v1/crm/pipelines/{$pipelineId}/stages", [
                'name' => 'Discovery',
                'type' => CrmStage::TYPE_OPEN,
                'probability' => 35,
                'sort_order' => 15,
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'discovery')
            ->assertJsonPath('data.probability', 35)
            ->json('data.id');

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->patchJson("/api/v1/crm/stages/{$stageId}", [
                'name' => 'Discovery validado',
                'probability' => 45,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Discovery validado')
            ->assertJsonPath('data.probability', 45);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->patchJson("/api/v1/crm/pipelines/{$pipelineId}", [
                'name' => 'Ventas enterprise 2026',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ventas enterprise 2026')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_pipeline_and_stage_delete_are_blocked_when_deals_depend_on_them(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();

        CrmDeal::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'title' => 'Deal dependiente',
            'value' => 7500,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->deleteJson("/api/v1/crm/pipelines/{$pipeline->getKey()}")
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->deleteJson("/api/v1/crm/stages/{$stage->getKey()}")
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_pipeline_stage_management_rejects_cross_tenant_records(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        [, $otherTenant] = $this->tenantOwner(email: 'other-pipeline-owner@example.com');
        $otherPipeline = $this->pipeline($otherTenant);
        $otherStage = $otherPipeline->stages()->where('key', 'new')->firstOrFail();

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->patchJson("/api/v1/crm/pipelines/{$otherPipeline->getKey()}", [
                'name' => 'Cruce indebido',
            ])
            ->assertNotFound();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->patchJson("/api/v1/crm/stages/{$otherStage->getKey()}", [
                'name' => 'Cruce indebido',
            ])
            ->assertNotFound();
    }

    public function test_viewer_cannot_manage_pipeline_or_stage_configuration(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $viewer = User::factory()->create(['email' => 'viewer-pipeline@example.com']);
        $viewer->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();
        $tenant->memberships()->create([
            'user_id' => $viewer->getKey(),
            'role' => Role::VIEWER,
            'accepted_at' => now(),
        ]);
        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();

        Sanctum::actingAs($viewer);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/crm/pipelines', ['name' => 'Bloqueado'])
            ->assertForbidden();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson("/api/v1/crm/pipelines/{$pipeline->getKey()}/stages", ['name' => 'Bloqueado'])
            ->assertForbidden();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->patchJson("/api/v1/crm/stages/{$stage->getKey()}", ['name' => 'Bloqueado'])
            ->assertForbidden();
    }

    public function test_tenant_can_create_update_and_move_a_deal_inside_its_pipeline(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();
        $proposal = $pipeline->stages()->where('key', 'proposal')->firstOrFail();
        $customer = CustomerProfile::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Cliente CRM',
            'email' => 'cliente@example.com',
            'phone' => '99999999',
            'preferred_channel' => 'whatsapp',
            'lifecycle_stage' => CustomerProfile::LIFECYCLE_PROSPECT,
            'customer_segment' => CustomerProfile::SEGMENT_PROSPECT,
        ]);
        $lead = Lead::query()->withoutGlobalScopes()->forceCreate([
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

    public function test_tenant_can_convert_lead_to_deal_with_customer_profile_path(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $lead = Lead::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Lead listo para deal',
            'email' => 'lead.convertido@example.com',
            'phone' => '99990000',
            'source' => 'landing',
            'message' => 'Quiere una propuesta.',
            'status' => 'new',
        ]);

        Sanctum::actingAs($owner);

        $dealId = (int) $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson("/api/v1/crm/leads/{$lead->getKey()}/convert-to-deal", [
                'value' => 9900,
                'expected_close_at' => now()->addWeek()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.lead_id', $lead->getKey())
            ->assertJsonPath('data.value', '9900.00')
            ->json('data.id');

        $this->assertDatabaseHas('crm_deals', [
            'id' => $dealId,
            'tenant_id' => (string) $tenant->getKey(),
            'lead_id' => $lead->getKey(),
            'title' => 'Oportunidad: Lead listo para deal',
        ]);

        $this->assertDatabaseHas('customer_profiles', [
            'tenant_id' => (string) $tenant->getKey(),
            'email' => 'lead.convertido@example.com',
        ]);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->getKey(),
            'tenant_id' => (string) $tenant->getKey(),
            'status' => 'converted',
        ]);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson("/api/v1/crm/leads/{$lead->getKey()}/convert-to-deal")
            ->assertOk()
            ->assertJsonPath('data.id', $dealId);
    }

    public function test_lead_conversion_rejects_cross_tenant_lead(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        [, $otherTenant] = $this->tenantOwner(email: 'other-lead-owner@example.com');
        $lead = Lead::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $otherTenant->getKey(),
            'name' => 'Lead externo',
            'email' => 'externo@example.com',
            'status' => 'new',
        ]);

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson("/api/v1/crm/leads/{$lead->getKey()}/convert-to-deal")
            ->assertNotFound();
    }

    public function test_viewer_cannot_convert_lead_to_deal(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $viewer = User::factory()->create(['email' => 'viewer-lead-convert@example.com']);
        $viewer->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();
        $tenant->memberships()->create([
            'user_id' => $viewer->getKey(),
            'role' => Role::VIEWER,
            'accepted_at' => now(),
        ]);
        $lead = Lead::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'name' => 'Lead bloqueado',
            'status' => 'new',
        ]);

        Sanctum::actingAs($viewer);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson("/api/v1/crm/leads/{$lead->getKey()}/convert-to-deal")
            ->assertForbidden();
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
        $wonStage = $pipeline->stages()->where('type', CrmStage::TYPE_WON)->firstOrFail();
        $lostStage = $pipeline->stages()->where('type', CrmStage::TYPE_LOST)->firstOrFail();

        CrmDeal::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $stage->getKey(),
            'title' => 'Deal abierto',
            'value' => 5000,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_OPEN,
            'expected_close_at' => now()->subDay()->toDateString(),
        ]);
        $wonDeal = CrmDeal::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $wonStage->getKey(),
            'title' => 'Deal ganado',
            'value' => 12000,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_WON,
            'closed_at' => now()->subDays(2),
        ]);
        $wonDeal->forceFill(['created_at' => now()->subDays(10)])->save();
        $lostDeal = CrmDeal::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => (string) $tenant->getKey(),
            'crm_pipeline_id' => $pipeline->getKey(),
            'crm_stage_id' => $lostStage->getKey(),
            'title' => 'Deal perdido',
            'value' => 4000,
            'currency' => 'HNL',
            'status' => CrmDeal::STATUS_LOST,
            'closed_at' => now()->subDays(2),
        ]);
        $lostDeal->forceFill(['created_at' => now()->subDays(6)])->save();

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->getJson('/api/v1/crm/pipeline-summary')
            ->assertOk()
            ->assertJsonPath('data.totals.open_count', 1)
            ->assertJsonPath('data.totals.open_value', '5000.00')
            ->assertJsonPath('data.totals.overdue_count', 1)
            ->assertJsonPath('data.conversion.closed_count', 2)
            ->assertJsonPath('data.conversion.won_count', 1)
            ->assertJsonPath('data.conversion.lost_count', 1)
            ->assertJsonPath('data.conversion.win_rate', '50.00')
            ->assertJsonPath('data.conversion.average_won_value', '12000.00')
            ->assertJsonPath('data.conversion.average_velocity_days', '6.00');
    }

    public function test_tenant_can_import_and_export_deals_inside_crm_gate(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $pipeline = $this->pipeline($tenant);
        $stage = $pipeline->stages()->where('key', 'new')->firstOrFail();

        Sanctum::actingAs($owner);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/crm/deals/import', [
                'deals' => [
                    [
                        'pipeline_id' => $pipeline->getKey(),
                        'stage_id' => $stage->getKey(),
                        'title' => '=CMD peligroso',
                        'value' => 6400,
                        'source' => 'csv_manual',
                    ],
                    [
                        'title' => 'Import default pipeline',
                        'value' => 3200,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('created_count', 2);

        $this->assertDatabaseHas('crm_deals', [
            'tenant_id' => (string) $tenant->getKey(),
            'title' => '=CMD peligroso',
            'source' => 'csv_manual',
        ]);

        $response = $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->get('/api/v1/crm/deals/export');

        $response->assertOk();
        self::assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        self::assertStringContainsString("'=CMD peligroso", $response->streamedContent());
    }

    public function test_viewer_cannot_import_or_export_deals(): void
    {
        [$owner, $tenant] = $this->tenantOwner();
        $viewer = User::factory()->create(['email' => 'viewer-crm-export@example.com']);
        $viewer->forceFill(['tenant_id' => (string) $tenant->getKey()])->save();
        $tenant->memberships()->create([
            'user_id' => $viewer->getKey(),
            'role' => Role::VIEWER,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($viewer);

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->postJson('/api/v1/crm/deals/import', [
                'deals' => [['title' => 'Bloqueado']],
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant-ID', (string) $tenant->getKey())
            ->get('/api/v1/crm/deals/export')
            ->assertForbidden();
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
        $deal = CrmDeal::query()->withoutGlobalScopes()->forceCreate([
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
        $deal = CrmDeal::query()->withoutGlobalScopes()->forceCreate([
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
        $deal = CrmDeal::query()->withoutGlobalScopes()->forceCreate([
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
        $deal = CrmDeal::query()->withoutGlobalScopes()->forceCreate([
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
            'api.crm.deals.import',
            'api.crm.deals.export',
            'api.crm.leads.convert-to-deal',
            'api.crm.deals.update',
            'api.crm.deals.stage.update',
            'api.crm.deals.destroy',
            'api.crm.pipelines.index',
            'api.crm.pipelines.store',
            'api.crm.pipelines.update',
            'api.crm.pipelines.destroy',
            'api.crm.pipelines.stages.store',
            'api.crm.stages.update',
            'api.crm.stages.destroy',
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
