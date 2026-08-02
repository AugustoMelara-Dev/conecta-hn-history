<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Ai\Workforce\AgentRole;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use App\Application\Ai\Workforce\Contracts\WorkforceRegistryAuthority;
use App\Application\Ai\Workforce\WorkforceAgentContext;
use App\Application\Ai\Workforce\WorkforceAgentResolver;
use App\Application\BusinessGraph\BusinessGraphEdge;
use App\Application\BusinessGraph\BusinessGraphFact;
use App\Application\BusinessGraph\BusinessGraphSnapshot;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

final class WorkforceAgentRoleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_covers_all_workforce_roles(): void
    {
        self::assertSame([
            'front_desk', 'revenue', 'service', 'support', 'operations', 'growth', 'manager',
        ], array_map(static fn (AgentRole $role): string => $role->value, AgentRoleCatalog::roles()));

        foreach (AgentRoleCatalog::roles() as $role) {
            $configuration = AgentRoleCatalog::configuration($role);
            self::assertNotSame('', $configuration->objective);
            self::assertNotEmpty($configuration->allowedJobs);
            self::assertNotEmpty($configuration->metricKeys);
        }
    }

    public function test_unknown_requested_role_is_denied_before_registry_selection(): void
    {
        $registry = Mockery::mock(WorkforceRegistryAuthority::class);
        $registry->shouldNotReceive('resolve');
        $resolver = new WorkforceAgentResolver($registry);

        $this->expectException(AiExecutionDeniedException::class);
        $resolver->resolve((string) Str::uuid(), null, 'unknown_role');
    }

    public function test_resolver_accepts_an_active_tenant_owned_version(): void
    {
        $tenantId = (string) \App\Models\Tenant::factory()->create()->getKey();
        $versionId = (string) Str::uuid();
        $this->insertRegistryVersion($tenantId, $versionId, 'manager', 'tenant.manager', 'active');
        $authority = Mockery::mock(WorkforceRegistryAuthority::class);
        $authority->shouldReceive('resolvePinned')->once()->andReturn($this->selection($versionId));

        $context = (new \App\Application\Ai\Workforce\WorkforceAgentResolver($authority))
            ->resolve($tenantId, $versionId, 'manager');

        self::assertSame($tenantId, $context->tenantId);
        self::assertSame($versionId, $context->agentVersionId);
        self::assertSame(AgentRole::MANAGER, $context->role);
    }

    public function test_resolver_denies_cross_tenant_pinned_versions(): void
    {
        $ownerTenantId = (string) \App\Models\Tenant::factory()->create()->getKey();
        $otherTenantId = (string) \App\Models\Tenant::factory()->create()->getKey();
        $versionId = (string) Str::uuid();
        $this->insertRegistryVersion($ownerTenantId, $versionId, 'manager', 'tenant.manager', 'active');

        $authority = Mockery::mock(WorkforceRegistryAuthority::class);
        $authority->shouldReceive('resolvePinned')->once()->andReturn($this->selection($versionId));
        $resolver = new \App\Application\Ai\Workforce\WorkforceAgentResolver($authority);

        $this->expectException(AiExecutionDeniedException::class);
        $resolver->resolve($otherTenantId, $versionId, 'manager');
    }

    public function test_resolver_denies_requested_role_mismatch(): void
    {
        $tenantId = (string) \App\Models\Tenant::factory()->create()->getKey();
        $versionId = (string) Str::uuid();
        $this->insertRegistryVersion($tenantId, $versionId, 'manager', 'tenant.manager', 'active');
        $authority = Mockery::mock(WorkforceRegistryAuthority::class);
        $authority->shouldReceive('resolvePinned')->once()->andReturn($this->selection($versionId));
        $resolver = new \App\Application\Ai\Workforce\WorkforceAgentResolver($authority);

        $this->expectException(AiExecutionDeniedException::class);
        $resolver->resolve($tenantId, $versionId, 'service');
    }

    public function test_agent_key_mapping_is_explicit_and_ambiguous_keys_are_rejected(): void
    {
        self::assertSame(AgentRole::FRONT_DESK, AgentRoleCatalog::forAgentKey('public.discovery'));
        self::assertSame(AgentRole::MANAGER, AgentRoleCatalog::forAgentKey('tenant.private_assistant'));
        self::assertSame(AgentRole::SERVICE, AgentRoleCatalog::forAgentKey('service'));
        self::assertNull(AgentRoleCatalog::forAgentKey('sales-support'));
    }

    public function test_context_redacts_runtime_secrets_and_rejects_other_tenant_snapshot(): void
    {
        $tenantId = (string) Str::uuid();
        $agentVersionId = (string) Str::uuid();
        $context = new WorkforceAgentContext(
            tenantId: $tenantId,
            agentVersionId: $agentVersionId,
            role: AgentRole::MANAGER,
            configuration: AgentRoleCatalog::configuration(AgentRole::MANAGER),
        );

        $fact = BusinessGraphFact::fromSource(
            tenantId: $tenantId,
            subjectType: 'tenant',
            subjectId: $tenantId,
            factKey: 'business.profile',
            value: [
                'api_key' => 'never-expose',
                'nested' => ['password' => 'never-expose', 'safe' => 'visible'],
            ],
            sourceType: 'tenant',
            sourceId: 'tenant:'.$tenantId,
        );
        $sensitiveFact = BusinessGraphFact::fromSource(
            tenantId: $tenantId,
            subjectType: 'tenant',
            subjectId: $tenantId,
            factKey: 'tenant.api_key',
            value: ['value' => 'fact-key-secret'],
            sourceType: 'secret_store',
            sourceId: 'secret:fact-key-secret',
        );
        $serialized = $context->systemContext(new BusinessGraphSnapshot($tenantId, [$fact, $sensitiveFact], []));
        self::assertSame($agentVersionId, $serialized['agentVersionId']);
        self::assertArrayNotHasKey('secretReference', $serialized);
        self::assertArrayNotHasKey('systemPrompt', $serialized);
        self::assertStringStartsWith('business-graph:v1:', $serialized['businessContextVersion']);
        self::assertSame('[redacted]', $serialized['businessContext']['facts'][0]['value']['api_key']);
        $serializedJson = json_encode($serialized, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('never-expose', $serializedJson);
        self::assertStringNotContainsString('fact-key-secret', $serializedJson);

        $this->expectException(InvalidArgumentException::class);
        $context->systemContext(new BusinessGraphSnapshot((string) Str::uuid(), [], []));
    }

    public function test_graph_version_is_stable_when_fact_order_changes(): void
    {
        $tenantId = (string) Str::uuid();
        $context = new WorkforceAgentContext(
            tenantId: $tenantId,
            agentVersionId: (string) Str::uuid(),
            role: AgentRole::MANAGER,
            configuration: AgentRoleCatalog::configuration(AgentRole::MANAGER),
        );

        $first = BusinessGraphFact::fromSource($tenantId, 'tenant', $tenantId, 'business.z', ['value' => 'z'], 'tenant', 'tenant:'.$tenantId.':z');
        $second = BusinessGraphFact::fromSource($tenantId, 'tenant', $tenantId, 'business.a', ['value' => 'a'], 'tenant', 'tenant:'.$tenantId.':a');
        $left = $context->systemContext(new BusinessGraphSnapshot($tenantId, [$first, $second], []));
        $right = $context->systemContext(new BusinessGraphSnapshot($tenantId, [$second, $first], []));

        self::assertSame($left['businessContextVersion'], $right['businessContextVersion']);
    }

    public function test_mixed_tenant_graph_records_are_rejected(): void
    {
        $tenantId = (string) Str::uuid();
        $context = new WorkforceAgentContext(
            tenantId: $tenantId,
            agentVersionId: (string) Str::uuid(),
            role: AgentRole::MANAGER,
            configuration: AgentRoleCatalog::configuration(AgentRole::MANAGER),
        );
        $foreignTenantId = (string) Str::uuid();
        $foreignFact = BusinessGraphFact::fromSource($foreignTenantId, 'tenant', $foreignTenantId, 'business.name', ['value' => 'foreign'], 'tenant', 'tenant:'.$foreignTenantId);

        $this->expectException(InvalidArgumentException::class);
        $context->systemContext(new BusinessGraphSnapshot($tenantId, [$foreignFact], []));
    }

    public function test_mixed_tenant_edges_are_rejected(): void
    {
        $tenantId = (string) Str::uuid();
        $context = new WorkforceAgentContext(
            tenantId: $tenantId,
            agentVersionId: (string) Str::uuid(),
            role: AgentRole::MANAGER,
            configuration: AgentRoleCatalog::configuration(AgentRole::MANAGER),
        );
        $foreignTenantId = (string) Str::uuid();
        $foreignEdge = BusinessGraphEdge::fromSource(
            tenantId: $foreignTenantId,
            fromType: 'tenant',
            fromId: $foreignTenantId,
            relation: 'owns',
            toType: 'product',
            toId: 'product-1',
            sourceType: 'product',
            sourceId: 'product:1',
        );

        $this->expectException(InvalidArgumentException::class);
        $context->systemContext(new BusinessGraphSnapshot($tenantId, [], [$foreignEdge]));
    }

    private function insertRegistryVersion(string $tenantId, string $versionId, string $role, string $definitionKey, string $lifecycleStatus): void
    {
        $definitionId = (string) Str::uuid();
        DB::table('ai_agent_definitions')->insert([
            'id' => $definitionId,
            'tenant_id' => $tenantId,
            'key' => $definitionKey,
            'name' => $definitionKey,
            'visibility' => 'tenant_private',
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_agent_versions')->insert([
            'id' => $versionId,
            'tenant_id' => $tenantId,
            'agent_definition_id' => $definitionId,
            'model_version_id' => null,
            'version' => 1,
            'checksum' => hash('sha256', $versionId),
            'configuration' => json_encode(['workforce_role' => $role], JSON_THROW_ON_ERROR),
            'released_at' => now(),
            'lifecycle_status' => $lifecycleStatus,
            'kill_switch_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function selection(string $agentVersionId): AiRuntimeSelection
    {
        return new AiRuntimeSelection(
            providerVersionId: (string) Str::uuid(),
            modelVersionId: (string) Str::uuid(),
            agentVersionId: $agentVersionId,
            promptVersionId: (string) Str::uuid(),
            providerKey: 'test',
            adapterKey: 'test.adapter',
            secretReference: 'test.secret.reference',
            modelKey: 'test-model',
            systemPrompt: 'test prompt',
            outputSchema: 'test.schema.v1',
            requiredCapabilities: ['structured_output'],
            maxOutputTokens: 64,
            releaseDecisionId: (string) Str::uuid(),
        );
    }
}
