<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\AiExecutionContextFactory;
use App\Application\Ai\Workforce\AgentRole;
use App\Application\Ai\Workforce\AgentRoleCatalog;
use App\Application\Ai\Workforce\WorkforceAgentContext;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkforceConversationContextTest extends TestCase
{
    public function test_execution_context_is_bounded_by_the_workforce_role(): void
    {
        $tenantId = (string) Str::uuid();
        config()->set('ai.capabilities.workforce_context_test', [
            'audience' => 'private',
            'billing_mode' => 'tenant',
        ]);
        $workforce = new WorkforceAgentContext(
            tenantId: $tenantId,
            agentVersionId: (string) Str::uuid(),
            role: AgentRole::FRONT_DESK,
            configuration: AgentRoleCatalog::configuration(AgentRole::FRONT_DESK),
        );

        $context = (new AiExecutionContextFactory)->create(
            capability: 'workforce_context_test',
            rateLimitIdentity: 'workforce-test:'.$tenantId,
            tenantId: $tenantId,
            authorized: true,
            entitled: true,
            allowedTools: ['tenant_catalog.read', 'unsafe.write'],
            workforceContext: $workforce,
        );

        self::assertSame(['tenant_catalog.read'], $context->allowedTools);
    }

    public function test_execution_context_rejects_a_workforce_context_from_another_tenant(): void
    {
        $tenantId = (string) Str::uuid();
        $otherTenantId = (string) Str::uuid();
        config()->set('ai.capabilities.workforce_context_test', [
            'audience' => 'private',
            'billing_mode' => 'tenant',
        ]);
        $workforce = new WorkforceAgentContext(
            tenantId: $otherTenantId,
            agentVersionId: (string) Str::uuid(),
            role: AgentRole::FRONT_DESK,
            configuration: AgentRoleCatalog::configuration(AgentRole::FRONT_DESK),
        );

        $this->expectException(AiExecutionDeniedException::class);
        (new AiExecutionContextFactory)->create(
            capability: 'workforce_context_test',
            rateLimitIdentity: 'workforce-test:'.$tenantId,
            tenantId: $tenantId,
            authorized: true,
            entitled: true,
            allowedTools: ['tenant_catalog.read'],
            workforceContext: $workforce,
        );
    }
}
