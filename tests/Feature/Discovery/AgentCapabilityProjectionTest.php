<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Application\Discovery\AgentCapabilityProjection;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AgentCapabilityProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_an_explicit_market_and_governed_active_agent_version(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertSame([], app(AgentCapabilityProjection::class)->forTenant((string) $tenant->id));

        $this->seedGovernedAgent($tenant->id, 'front_desk', true);

        $capabilities = app(AgentCapabilityProjection::class)->forTenant((string) $tenant->id);

        self::assertCount(1, $capabilities);
        self::assertSame('private_tenant_agent', $capabilities[0]['capability']);
        self::assertTrue($capabilities[0]['marketContextRequired']);
    }

    public function test_inactive_versions_and_unpublished_policies_are_not_projected(): void
    {
        $tenant = Tenant::factory()->create();
        $this->seedGovernedAgent($tenant->id, 'front_desk', false);

        self::assertSame([], app(AgentCapabilityProjection::class)->forTenant((string) $tenant->id));
    }

    private function seedGovernedAgent(string $tenantId, string $role, bool $active): void
    {
        $packVersionId = DB::table('market_pack_versions')->value('id');
        if ($packVersionId === null) {
            $this->markTestSkipped('Global market fixtures are not available in this database.');
        }

        DB::table('tenant_markets')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'id' => (string) (DB::table('tenant_markets')->where('tenant_id', $tenantId)->value('id') ?: Str::uuid()),
                'market_pack_version_id' => $packVersionId,
                'country_code' => 'HN',
                'locale_code' => 'es-HN',
                'currency_code' => 'HNL',
                'timezone_name' => 'America/Tegucigalpa',
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $definitionId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $policyId = (string) Str::uuid();
        $decisionId = (string) Str::uuid();
        $now = now();

        DB::table('ai_agent_definitions')->insert([
            'id' => $definitionId,
            'tenant_id' => $tenantId,
            'key' => 'workforce.'.$role,
            'name' => 'Workforce '.$role,
            'visibility' => 'tenant_private',
            'status' => 'active',
            'metadata' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('ai_agent_versions')->insert([
            'id' => $versionId,
            'tenant_id' => $tenantId,
            'agent_definition_id' => $definitionId,
            'model_version_id' => null,
            'version' => 1,
            'checksum' => hash('sha256', $versionId),
            'configuration' => json_encode([
                'capability' => 'private_tenant_agent',
                'workforce_role' => $role,
                'required_capabilities' => ['structured_output'],
            ], JSON_THROW_ON_ERROR),
            'released_at' => $active ? $now : null,
            'lifecycle_status' => $active ? 'active' : 'draft',
            'kill_switch_enabled' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('ai_release_policies')->insert([
            'id' => $policyId,
            'tenant_id' => $tenantId,
            'key' => 'workforce.default',
            'name' => 'Workforce policy',
            'checksum' => hash('sha256', $policyId),
            'rules' => json_encode(['required_capabilities' => ['structured_output']], JSON_THROW_ON_ERROR),
            'status' => $active ? 'active' : 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('ai_release_decisions')->insert([
            'id' => $decisionId,
            'tenant_id' => $tenantId,
            'release_policy_id' => $policyId,
            'evaluation_run_id' => null,
            'agent_version_id' => $versionId,
            'decision' => 'approved',
            'decided_by' => 'test',
            'evidence_hash' => hash('sha256', $decisionId),
            'rationale' => json_encode([], JSON_THROW_ON_ERROR),
            'decided_at' => $now,
            'valid_from' => $now,
            'valid_until' => null,
            'revoked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('ai_agent_activations')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'scope_type' => 'tenant',
            'scope_key' => $tenantId,
            'agent_version_id' => $versionId,
            'rollout_percentage' => 100,
            'cohort_salt' => hash('sha256', $tenantId),
            'release_decision_id' => $decisionId,
            'previous_activation_id' => null,
            'effective_from' => $now,
            'effective_until' => null,
            'activated_by' => 'test',
            'reason' => 'test',
            'lock_version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
