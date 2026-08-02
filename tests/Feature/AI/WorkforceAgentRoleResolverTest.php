<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Registry\AiRuntimeSelection;
use App\Application\Ai\Workforce\AgentRole;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use App\Application\Ai\Workforce\WorkforceAgentContext;
use App\Application\Ai\Workforce\WorkforceAgentResolver;
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

        $serialized = $context->systemContext(new BusinessGraphSnapshot($tenantId, [], []));
        self::assertSame($agentVersionId, $serialized['agentVersionId']);
        self::assertArrayNotHasKey('secretReference', $serialized);
        self::assertArrayNotHasKey('systemPrompt', $serialized);
        self::assertStringStartsWith('business-graph:v1:', $serialized['businessContextVersion']);

        $this->expectException(InvalidArgumentException::class);
        $context->systemContext(new BusinessGraphSnapshot((string) Str::uuid(), [], []));
    }

    public function test_context_cannot_be_constructed_with_unpinned_selection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WorkforceAgentContext(
            tenantId: (string) Str::uuid(),
            agentVersionId: (string) Str::uuid(),
            role: AgentRole::SERVICE,
            configuration: AgentRoleCatalog::configuration(AgentRole::SERVICE),
            selection: new AiRuntimeSelection(
                providerVersionId: (string) Str::uuid(),
                modelVersionId: (string) Str::uuid(),
                agentVersionId: (string) Str::uuid(),
                promptVersionId: (string) Str::uuid(),
                providerKey: 'test',
                adapterKey: 'test.adapter',
                secretReference: 'secret.reference',
                modelKey: 'test-model',
                systemPrompt: 'test prompt',
                outputSchema: 'test.schema.v1',
                requiredCapabilities: ['structured_output'],
                maxOutputTokens: 64,
                releaseDecisionId: (string) Str::uuid(),
            ),
        );
    }
}
