<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Workforce\AgentRole;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use App\Application\Ai\Workforce\WorkforceAgentContext;
use App\Application\Ai\Workforce\WorkforceAgentResolver;
use App\Application\BusinessGraph\BusinessGraphFact;
use App\Application\BusinessGraph\BusinessGraphSnapshot;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

final class WorkforceAgentRoleResolverTest extends TestCase
{
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
        $registry = Mockery::mock(AiRegistryResolver::class);
        $registry->shouldNotReceive('resolve');
        $resolver = new WorkforceAgentResolver($registry);

        $this->expectException(AiExecutionDeniedException::class);
        $resolver->resolve((string) Str::uuid(), null, 'unknown_role');
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
        $serialized = $context->systemContext(new BusinessGraphSnapshot($tenantId, [$fact], []));
        self::assertSame($agentVersionId, $serialized['agentVersionId']);
        self::assertArrayNotHasKey('secretReference', $serialized);
        self::assertArrayNotHasKey('systemPrompt', $serialized);
        self::assertStringStartsWith('business-graph:v1:', $serialized['businessContextVersion']);
        self::assertSame('[redacted]', $serialized['businessContext']['facts'][0]['value']['api_key']);
        self::assertStringNotContainsString('never-expose', json_encode($serialized, JSON_THROW_ON_ERROR));

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
}
