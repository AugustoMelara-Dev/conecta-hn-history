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

final class AgentActionExecutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_read_action_runs_the_published_workflow_and_writes_a_sanitized_audit(): void
    {
        [$tenant] = $this->tenant();
        $this->publishWorkflow($tenant, 'agent.read', []);
        $this->policy($tenant, 'agent.read', 'read');

        $result = $this->executor()->execute($this->request($tenant, 'agent.read', 'read', [
            'customer_id' => 'customer-1',
            'api_token' => 'must-not-be-audited',
        ]));

        self::assertTrue($result->executed);
        self::assertSame('completed', $result->status);
        self::assertSame('completed', $result->reasonCode);
        self::assertNotNull($result->workflowRunId);
        self::assertSame('agent.read', $result->audit->actionKey);
        self::assertArrayNotHasKey('api_token', $result->audit->toArray()['metadata']);
        $this->assertDatabaseHas('dovra_agent_action_audits', [
            'id' => $result->audit->id,
            'tenant_id' => $tenant->getKey(),
            'action_key' => 'agent.read',
            'policy_code' => 'policy_authorized',
            'workflow_run_id' => $result->workflowRunId,
        ]);
    }

    public function test_denied_and_confirmation_required_actions_do_not_start_workflows(): void
    {
        [$tenant] = $this->tenant();
        $this->publishWorkflow($tenant, 'agent.secure', []);
        $this->policy($tenant, 'agent.secure', 'read');

        $denied = $this->executor()->execute($this->request($tenant, 'agent.secure', 'economic'));

        self::assertFalse($denied->executed);
        self::assertSame('blocked', $denied->status);
        self::assertSame('risk_denied', $denied->reasonCode);

        $this->policy($tenant, 'agent.confirmed', 'mutation');
        $this->publishWorkflow($tenant, 'agent.confirmed', []);
        $confirmationRequired = $this->executor()->execute($this->request($tenant, 'agent.confirmed', 'mutation'));

        self::assertFalse($confirmationRequired->executed);
        self::assertSame('confirmation_required', $confirmationRequired->reasonCode);
        self::assertSame(0, DB::table('automation_workflow_runs')->where('tenant_id', $tenant->getKey())->count());
        self::assertSame(0, DB::table('automation_integration_idempotency')
            ->where('tenant_id', $tenant->getKey())
            ->where('scope', 'agent_action:agent.confirmed')
            ->count());
    }

    public function test_tool_failure_is_returned_as_a_structured_failed_result_without_direct_provider_execution(): void
    {
        [$tenant] = $this->tenant();
        $this->policy($tenant, 'agent.sync', 'read');
        $this->publishWorkflow($tenant, 'agent.sync', [[
            'id' => 'missing-tool',
            'type' => 'tool',
            'action' => 'missing-tool-handler',
        ]]);

        $result = $this->executor()->execute($this->request($tenant, 'agent.sync', 'read'));

        self::assertFalse($result->executed);
        self::assertSame('failed', $result->status);
        self::assertSame('tool_handler_missing', $result->reasonCode);
        self::assertNotNull($result->workflowRunId);
    }

    public function test_tenant_context_mismatch_is_blocked_and_audited_against_the_active_tenant_only(): void
    {
        [$activeTenant] = $this->tenant();
        [$foreignTenant] = $this->tenant(false);

        $result = $this->executor()->execute($this->request($foreignTenant, 'agent.read', 'read'));

        self::assertFalse($result->executed);
        self::assertSame('tenant_context_mismatch', $result->reasonCode);
        self::assertSame((string) $activeTenant->getKey(), $result->audit->tenantId);
        $this->assertDatabaseHas('dovra_agent_action_audits', [
            'id' => $result->audit->id,
            'tenant_id' => $activeTenant->getKey(),
            'policy_code' => 'tenant_context_mismatch',
        ]);
        $this->assertDatabaseMissing('automation_workflow_runs', ['tenant_id' => $foreignTenant->getKey()]);
    }

    private function executor(): AgentActionExecutor
    {
        $repository = new DatabaseWorkflowRepository(DB::connection());
        $policy = new ToolPolicyEngine($repository);

        return new AgentActionExecutor(
            policyEngine: $policy,
            confirmations: app(AiToolConfirmationService::class),
            workflowRuntime: new WorkflowRuntime(
                definitions: new WorkflowDefinitionService($repository),
                runs: $repository,
                policyEngine: $policy,
            ),
            repository: $repository,
            tenantContext: app(TenantContext::class),
        );
    }

    private function publishWorkflow(Tenant $tenant, string $key, array $steps): void
    {
        $repository = new DatabaseWorkflowRepository(DB::connection());
        $definitions = new WorkflowDefinitionService($repository);
        $draft = $definitions->createDraft((string) $tenant->getKey(), $key, ['steps' => $steps], (string) Str::uuid());
        $definitions->publishDraft((string) $tenant->getKey(), $key, $draft['version_id'], 'v1', (string) Str::uuid());
    }

    private function policy(Tenant $tenant, string $actionKey, string $maxRisk): void
    {
        DB::table('automation_tool_policies')->updateOrInsert([
            'tenant_id' => $tenant->getKey(),
            'tool' => $actionKey,
            'tool_version' => 'agent-action-v1',
        ], [
            'id' => (string) Str::uuid(),
            'allowed_roles' => json_encode(['agent'], JSON_THROW_ON_ERROR),
            'allowed_plans' => json_encode(['pro'], JSON_THROW_ON_ERROR),
            'allowed_channels' => json_encode(['workforce'], JSON_THROW_ON_ERROR),
            'max_risk' => $maxRisk,
            'confirmation_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function request(Tenant $tenant, string $actionKey, string $risk, array $input = []): AgentActionRequest
    {
        return AgentActionRequest::fromWorkforce(
            (string) $tenant->getKey(),
            (string) Str::uuid(),
            (string) Str::uuid(),
            $actionKey,
            $input,
            'agent-action:'.$actionKey.':'.Str::uuid(),
            $risk,
        );
    }

    /** @return array{Tenant, User} */
    private function tenant(bool $activateContext = true): array
    {
        /** @var array{Tenant, User} $result */
        $result = Model::withoutEvents(function (): array {
            $actor = User::factory()->create();
            $tenant = Tenant::factory()->forUser($actor)->pro()->create();

            return [$tenant, $actor];
        });

        if ($activateContext) {
            $context = app(TenantContext::class);
            $context->reset();
            $context->setTenant((string) $result[0]->getKey());
        }

        return $result;
    }
}
