<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Application\Automation\AgentAction\AgentActionExecutor;
use App\Application\Automation\AgentAction\AgentActionRequest;
use App\Application\Automation\ToolPolicyEngine;
use App\Application\Automation\WorkflowDefinitionService;
use App\Application\Automation\WorkflowRuntime;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\AI\Tools\AiToolConfirmationService;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AgentActionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_replayed_agent_action_does_not_start_a_second_workflow_or_duplicate_its_audit(): void
    {
        [$tenant] = $this->tenant();
        $repository = new DatabaseWorkflowRepository(DB::connection());
        $definitions = new WorkflowDefinitionService($repository);
        $draft = $definitions->createDraft((string) $tenant->getKey(), 'agent.lookup', ['steps' => []], (string) Str::uuid());
        $definitions->publishDraft((string) $tenant->getKey(), 'agent.lookup', $draft['version_id'], 'v1', (string) Str::uuid());
        DB::table('automation_tool_policies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'tool' => 'agent.lookup',
            'tool_version' => 'agent-action-v1',
            'allowed_roles' => json_encode(['agent'], JSON_THROW_ON_ERROR),
            'allowed_plans' => json_encode(['pro'], JSON_THROW_ON_ERROR),
            'allowed_channels' => json_encode(['workforce'], JSON_THROW_ON_ERROR),
            'max_risk' => 'read',
            'confirmation_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $policy = new ToolPolicyEngine($repository);
        $executor = new AgentActionExecutor(
            policyEngine: $policy,
            confirmations: app(AiToolConfirmationService::class),
            workflowRuntime: new WorkflowRuntime(new WorkflowDefinitionService($repository), $repository, $policy),
            repository: $repository,
            tenantContext: app(TenantContext::class),
        );
        $request = AgentActionRequest::fromWorkforce(
            (string) $tenant->getKey(),
            (string) Str::uuid(),
            (string) Str::uuid(),
            'agent.lookup',
            ['query' => 'open invoices'],
            'stable-agent-action-key',
            'read',
        );

        $first = $executor->execute($request);
        $replayed = $executor->execute($request);

        self::assertTrue($first->executed);
        self::assertFalse($replayed->executed);
        self::assertSame('idempotency_replay', $replayed->reasonCode);
        self::assertSame($first->audit->id, $replayed->audit->id);
        self::assertSame(1, DB::table('automation_workflow_runs')->where('tenant_id', $tenant->getKey())->count());
        self::assertSame(1, DB::table('dovra_agent_action_audits')->where('tenant_id', $tenant->getKey())->count());
    }

    /** @return array{Tenant, User} */
    private function tenant(): array
    {
        /** @var array{Tenant, User} $result */
        $result = Model::withoutEvents(function (): array {
            $actor = User::factory()->create();
            $tenant = Tenant::factory()->forUser($actor)->pro()->create();

            return [$tenant, $actor];
        });
        $context = app(TenantContext::class);
        $context->reset();
        $context->setTenant((string) $result[0]->getKey());

        return $result;
    }
}
