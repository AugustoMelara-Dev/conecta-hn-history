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
        $draft = $definitions->createDraft((string) $tenant->getKey(), 'agent.lookup', [
            'steps' => [[
                'id' => 'counted-step',
                'type' => 'tool',
                'action' => 'counted-tool',
            ]],
        ], (string) Str::uuid());
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
        $handlerCalls = 0;
        $executor = new AgentActionExecutor(
            policyEngine: $policy,
            confirmations: app(AiToolConfirmationService::class),
            workflowRuntime: new WorkflowRuntime(
                new WorkflowDefinitionService($repository),
                $repository,
                $policy,
                [
                    'counted-tool' => function () use (&$handlerCalls): array {
                        $handlerCalls++;

                        return ['output' => ['counted' => true]];
                    },
                ],
            ),
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
        $tenantStateBeforeReplay = $this->automationState((string) $tenant->getKey());
        $tenantHashBeforeReplay = $this->automationStateHash($tenantStateBeforeReplay);
        $tenantCountsBeforeReplay = $this->automationStateCounts($tenantStateBeforeReplay);
        $runsBeforeReplay = DB::table('automation_workflow_runs')->where('tenant_id', $tenant->getKey())->count();
        $auditsBeforeReplay = DB::table('dovra_agent_action_audits')->where('tenant_id', $tenant->getKey())->count();
        $replayed = $executor->execute($request);

        self::assertTrue($first->executed);
        self::assertSame('policy_authorized', $first->audit->policyCode);
        self::assertFalse($replayed->executed);
        self::assertSame('idempotency_replay', $replayed->reasonCode);
        self::assertSame($first->audit->id, $replayed->audit->id);
        self::assertSame('policy_authorized', $replayed->audit->policyCode);
        self::assertSame($first->audit->toArray(), $replayed->audit->toArray());
        self::assertSame($runsBeforeReplay, DB::table('automation_workflow_runs')->where('tenant_id', $tenant->getKey())->count());
        self::assertSame($auditsBeforeReplay, DB::table('dovra_agent_action_audits')->where('tenant_id', $tenant->getKey())->count());
        self::assertSame(1, $handlerCalls);
        $audit = DB::table('dovra_agent_action_audits')->where('id', $first->audit->id)->first();
        self::assertNotNull($audit);
        self::assertSame('policy_authorized', (string) $audit->policy_code);
        self::assertSame('completed', (string) $audit->status);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('completed', $metadata['workflow_status'] ?? null);
        self::assertSame('completed', $metadata['workflow_reason_code'] ?? null);
        $tenantStateAfterReplay = $this->automationState((string) $tenant->getKey());
        self::assertSame($tenantStateBeforeReplay, $tenantStateAfterReplay);
        self::assertSame($tenantHashBeforeReplay, $this->automationStateHash($tenantStateAfterReplay));
        self::assertSame($tenantCountsBeforeReplay, $this->automationStateCounts($tenantStateAfterReplay));
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function automationState(string $tenantId): array
    {
        $tables = [
            'dovra_agent_action_audits',
            'automation_workflow_runs',
            'automation_workflow_steps',
            'automation_workflow_step_attempts',
            'automation_integration_idempotency',
            'dovra_agent_outcomes',
        ];
        $state = [];
        foreach ($tables as $table) {
            $state[$table] = DB::table($table)
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->values()
                ->all();
        }

        return $state;
    }

    /** @param array<string, list<array<string, mixed>>> $state */
    private function automationStateHash(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, list<array<string, mixed>>> $state
     * @return array<string, int>
     */
    private function automationStateCounts(array $state): array
    {
        return array_map(static fn (array $rows): int => count($rows), $state);
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
