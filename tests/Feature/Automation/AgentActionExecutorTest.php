<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Application\Automation\AgentAction\AgentActionExecutor;
use App\Application\Automation\AgentAction\AgentActionRequest;
use App\Application\Automation\AgentAction\AgentActionResult;
use App\Application\Automation\ToolPolicyEngine;
use App\Application\Automation\WorkflowDefinitionService;
use App\Application\Automation\WorkflowRuntime;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\AI\Tools\AiToolConfirmationService;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use App\Models\AiToolConfirmation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        self::assertSame('policy_authorized', $result->audit->policyCode);
        self::assertArrayNotHasKey('api_token', $result->audit->toArray()['metadata']);
        $this->assertDatabaseHas('dovra_agent_action_audits', [
            'id' => $result->audit->id,
            'tenant_id' => $tenant->getKey(),
            'action_key' => 'agent.read',
            'policy_code' => 'policy_authorized',
            'workflow_run_id' => $result->workflowRunId,
        ]);
        $audit = DB::table('dovra_agent_action_audits')->where('id', $result->audit->id)->first();
        self::assertNotNull($audit);
        self::assertSame('completed', (string) $audit->status);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('completed', $metadata['workflow_status'] ?? null);
        self::assertSame('completed', $metadata['workflow_reason_code'] ?? null);
        self::assertSame($metadata, $result->audit->metadata);
        self::assertSame('completed', $result->reasonCode);
    }

    public function test_confirmation_authorizes_when_conversation_and_confirmation_share_the_same_actor(): void
    {
        [$tenant, $actor] = $this->tenant();
        [$foreignTenant, $foreignActor] = $this->tenant(false);
        $actionKey = 'agent.confirmed.same-actor';
        $conversationId = (string) Str::uuid();
        $confirmationId = (string) Str::uuid();
        $confirmationToken = 'same-actor-confirmation-token';

        $this->publishWorkflow($tenant, $actionKey, []);
        $this->policy($tenant, $actionKey, 'mutation', true);
        $this->privateConversation($tenant, $actor, $conversationId);
        $request = $this->request(
            tenant: $tenant,
            actionKey: $actionKey,
            risk: 'mutation',
            input: [
                'customer_id' => 'same-actor-customer',
                '_confirmation_id' => $confirmationId,
                '_confirmation_token' => $confirmationToken,
            ],
            agentVersionId: (string) Str::uuid(),
            conversationId: $conversationId,
        );
        $this->completedConfirmation($tenant, $actor, $request, $confirmationId, $confirmationToken);
        $confirmationBefore = (array) DB::table('ai_tool_confirmations')->where('id', $confirmationId)->first();
        $conversationBefore = (array) DB::table('ai_conversations')->where('id', $conversationId)->first();

        $this->privateConversation($foreignTenant, $foreignActor, (string) Str::uuid());
        $foreignStateBefore = $this->automationState((string) $foreignTenant->getKey());
        $foreignHashBefore = $this->automationStateHash($foreignStateBefore);
        $foreignCountsBefore = $this->automationStateCounts($foreignStateBefore);

        $result = $this->executor()->execute($request);

        self::assertTrue($result->executed);
        self::assertSame('completed', $result->status);
        self::assertSame('completed', $result->reasonCode);
        self::assertSame('policy_authorized', $result->audit->policyCode);
        self::assertSame($confirmationId, $result->audit->confirmationId);
        self::assertSame((string) $tenant->getKey(), $result->audit->tenantId);
        self::assertSame(1, DB::table('automation_workflow_runs')->where('tenant_id', $tenant->getKey())->count());
        self::assertSame(1, DB::table('automation_integration_idempotency')
            ->where('tenant_id', $tenant->getKey())
            ->where('scope', 'agent_action:'.$actionKey)
            ->count());
        self::assertSame($confirmationBefore, (array) DB::table('ai_tool_confirmations')->where('id', $confirmationId)->first());
        self::assertSame($conversationBefore, (array) DB::table('ai_conversations')->where('id', $conversationId)->first());
        self::assertSame($foreignHashBefore, $this->automationStateHash($this->automationState((string) $foreignTenant->getKey())));
        self::assertSame($foreignCountsBefore, $this->automationStateCounts($this->automationState((string) $foreignTenant->getKey())));
    }

    public function test_confirmation_fails_closed_when_same_tenant_conversation_actor_differs(): void
    {
        [$tenant, $conversationActor] = $this->tenant();
        [$foreignTenant, $foreignActor] = $this->tenant(false);
        $confirmationActor = User::factory()->create();
        $this->membership($tenant, $confirmationActor, 'operator');
        self::assertTrue($confirmationActor->fresh()->canAccessTenant($tenant));
        $actionKey = 'agent.confirmed.distinct-actor';
        $conversationId = (string) Str::uuid();
        $confirmationId = (string) Str::uuid();
        $confirmationToken = 'distinct-actor-confirmation-token';

        $this->publishWorkflow($tenant, $actionKey, []);
        $this->policy($tenant, $actionKey, 'mutation', true);
        $this->privateConversation($tenant, $conversationActor, $conversationId);
        $request = $this->request(
            tenant: $tenant,
            actionKey: $actionKey,
            risk: 'mutation',
            input: [
                'customer_id' => 'distinct-actor-customer',
                '_confirmation_id' => $confirmationId,
                '_confirmation_token' => $confirmationToken,
            ],
            agentVersionId: (string) Str::uuid(),
            conversationId: $conversationId,
        );
        $this->completedConfirmation($tenant, $confirmationActor, $request, $confirmationId, $confirmationToken);
        $confirmationBefore = (array) DB::table('ai_tool_confirmations')->where('id', $confirmationId)->first();
        $conversationBefore = (array) DB::table('ai_conversations')->where('id', $conversationId)->first();

        $foreignSentinel = $this->foreignSentinel($foreignTenant, $foreignActor, 'distinct-actor');
        $protectedBefore = $this->protectedEffectState((string) $tenant->getKey());
        $counterBefore = $this->handlerProviderCounterState();
        $confirmationBefore = (array) DB::table('ai_tool_confirmations')->where('id', $confirmationId)->first();
        $conversationBefore = (array) DB::table('ai_conversations')->where('id', $conversationId)->first();

        $result = $this->executor()->execute($request);

        $this->assertBindingDenialInvariants(
            tenant: $tenant,
            request: $request,
            result: $result,
            confirmationBefore: $confirmationBefore,
            conversationBefore: $conversationBefore,
            protectedBefore: $protectedBefore,
            counterBefore: $counterBefore,
            foreignSentinel: $foreignSentinel,
            leakedSecrets: [
                (string) $conversationActor->getKey(),
                (string) $confirmationActor->getKey(),
                $confirmationToken,
            ],
        );
    }

    public function test_confirmation_fails_closed_for_missing_or_ineligible_conversations(): void
    {
        [$tenant, $actor] = $this->tenant();
        [$foreignTenant, $foreignActor] = $this->tenant(false);
        $cases = [
            'missing' => null,
            'platform_public' => [
                'visibility' => 'platform_public',
                'status' => 'active',
                'actor_id' => (string) $actor->getKey(),
            ],
            'closed' => [
                'visibility' => 'tenant_private',
                'status' => 'closed',
                'actor_id' => (string) $actor->getKey(),
            ],
            'missing_actor' => [
                'visibility' => 'tenant_private',
                'status' => 'active',
                'actor_id' => null,
            ],
        ];

        foreach ($cases as $label => $conversation) {
            $actionKey = 'agent.confirmed.ineligible.'.$label;
            $conversationId = (string) Str::uuid();
            $confirmationId = (string) Str::uuid();
            $confirmationToken = 'ineligible-token-'.$label;
            $this->publishWorkflow($tenant, $actionKey, []);
            $this->policy($tenant, $actionKey, 'mutation', true);
            if ($conversation !== null) {
                $this->conversationRow(
                    tenant: $tenant,
                    conversationId: $conversationId,
                    visibility: (string) $conversation['visibility'],
                    status: (string) $conversation['status'],
                    actorId: $conversation['actor_id'],
                );
            }
            $request = $this->request(
                tenant: $tenant,
                actionKey: $actionKey,
                risk: 'mutation',
                input: [
                    'case' => $label,
                    '_confirmation_id' => $confirmationId,
                    '_confirmation_token' => $confirmationToken,
                ],
                agentVersionId: (string) Str::uuid(),
                conversationId: $conversationId,
            );
            $this->completedConfirmation($tenant, $actor, $request, $confirmationId, $confirmationToken);
            $foreignSentinel = $this->foreignSentinel($foreignTenant, $foreignActor, 'ineligible-'.$label);
            $protectedBefore = $this->protectedEffectState((string) $tenant->getKey());
            $counterBefore = $this->handlerProviderCounterState();
            $confirmationBefore = (array) DB::table('ai_tool_confirmations')->where('id', $confirmationId)->first();
            $conversationBefore = $conversation === null
                ? null
                : (array) DB::table('ai_conversations')->where('id', $conversationId)->first();

            $result = $this->executor()->execute($request);

            $this->assertBindingDenialInvariants(
                tenant: $tenant,
                request: $request,
                result: $result,
                confirmationBefore: $confirmationBefore,
                conversationBefore: $conversationBefore,
                protectedBefore: $protectedBefore,
                counterBefore: $counterBefore,
                foreignSentinel: $foreignSentinel,
                leakedSecrets: [$confirmationToken, $conversationId, (string) $actor->getKey()],
            );
        }
    }

    public function test_confirmation_rejects_a_foreign_tenant_conversation_without_foreign_mutation(): void
    {
        [$tenant, $actor] = $this->tenant();
        [$foreignTenant, $foreignActor] = $this->tenant(false);
        $actionKey = 'agent.confirmed.foreign-conversation';
        $foreignSentinel = $this->foreignSentinel($foreignTenant, $foreignActor, 'foreign-conversation');
        $conversationId = $foreignSentinel['conversationId'];
        $confirmationId = (string) Str::uuid();
        $confirmationToken = 'foreign-conversation-token';
        $this->publishWorkflow($tenant, $actionKey, []);
        $this->policy($tenant, $actionKey, 'mutation', true);
        $request = $this->request(
            tenant: $tenant,
            actionKey: $actionKey,
            risk: 'mutation',
            input: [
                'foreign' => true,
                '_confirmation_id' => $confirmationId,
                '_confirmation_token' => $confirmationToken,
            ],
            agentVersionId: (string) Str::uuid(),
            conversationId: $conversationId,
        );
        $this->completedConfirmation($tenant, $actor, $request, $confirmationId, $confirmationToken);
        $protectedBefore = $this->protectedEffectState((string) $tenant->getKey());
        $counterBefore = $this->handlerProviderCounterState();
        $confirmationBefore = (array) DB::table('ai_tool_confirmations')->where('id', $confirmationId)->first();

        $result = $this->executor()->execute($request);

        $this->assertBindingDenialInvariants(
            tenant: $tenant,
            request: $request,
            result: $result,
            confirmationBefore: $confirmationBefore,
            conversationBefore: null,
            protectedBefore: $protectedBefore,
            counterBefore: $counterBefore,
            foreignSentinel: $foreignSentinel,
            leakedSecrets: [$confirmationToken, $conversationId, (string) $actor->getKey()],
        );
    }

    public function test_confirmation_rejects_a_malformed_conversation_uuid_before_any_protected_effect(): void
    {
        [$tenant, $actor] = $this->tenant();
        [$foreignTenant, $foreignActor] = $this->tenant(false);
        $actionKey = 'agent.confirmed.malformed-conversation';
        $confirmationId = (string) Str::uuid();
        $confirmationToken = 'malformed-conversation-token';
        $this->publishWorkflow($tenant, $actionKey, []);
        $this->policy($tenant, $actionKey, 'mutation', true);
        $request = $this->malformedRequest(
            tenant: $tenant,
            actionKey: $actionKey,
            risk: 'mutation',
            input: [
                'malformed' => true,
                '_confirmation_id' => $confirmationId,
                '_confirmation_token' => $confirmationToken,
            ],
            agentVersionId: (string) Str::uuid(),
            conversationId: 'not-a-uuid',
        );
        $this->completedConfirmation($tenant, $actor, $request, $confirmationId, $confirmationToken);
        $foreignSentinel = $this->foreignSentinel($foreignTenant, $foreignActor, 'malformed-conversation');
        $protectedBefore = $this->protectedEffectState((string) $tenant->getKey());
        $counterBefore = $this->handlerProviderCounterState();
        $confirmationBefore = (array) DB::table('ai_tool_confirmations')->where('id', $confirmationId)->first();

        $result = $this->executor()->execute($request);

        $this->assertBindingDenialInvariants(
            tenant: $tenant,
            request: $request,
            result: $result,
            confirmationBefore: $confirmationBefore,
            conversationBefore: null,
            protectedBefore: $protectedBefore,
            counterBefore: $counterBefore,
            foreignSentinel: $foreignSentinel,
            leakedSecrets: [$confirmationToken, 'not-a-uuid', (string) $actor->getKey()],
        );
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
        self::assertSame('policy_authorized', $result->audit->policyCode);
        $audit = DB::table('dovra_agent_action_audits')->where('id', $result->audit->id)->first();
        self::assertNotNull($audit);
        self::assertSame('policy_authorized', (string) $audit->policy_code);
        self::assertSame('failed', (string) $audit->status);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('failed', $metadata['workflow_status'] ?? null);
        self::assertSame('tool_handler_missing', $metadata['workflow_reason_code'] ?? null);
        self::assertSame($metadata, $result->audit->metadata);
    }

    public function test_caught_workflow_runtime_failure_keeps_authorization_and_sanitizes_failure_metadata(): void
    {
        [$tenant] = $this->tenant();
        [$foreignTenant] = $this->tenant(false);
        $this->policy($foreignTenant, 'agent.foreign.sentinel', 'read');
        $this->publishWorkflow($foreignTenant, 'agent.foreign.sentinel', []);
        DB::table('dovra_agent_action_audits')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->getKey(),
            'agent_version_id' => (string) Str::uuid(),
            'conversation_id' => (string) Str::uuid(),
            'action_key' => 'agent.foreign.sentinel',
            'idempotency_key' => 'foreign-agent-action-sentinel',
            'input_hash' => hash('sha256', 'foreign-sentinel'),
            'risk' => 'read',
            'policy_code' => 'policy_authorized',
            'confirmation_id' => null,
            'workflow_run_id' => null,
            'outcome_id' => null,
            'status' => 'authorized',
            'metadata' => json_encode(['sentinel' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignStateBefore = $this->automationState((string) $foreignTenant->getKey());
        $foreignHashBefore = $this->automationStateHash($foreignStateBefore);
        $foreignCountsBefore = $this->automationStateCounts($foreignStateBefore);
        $this->policy($tenant, 'agent.missing', 'read');

        $result = $this->executor()->execute($this->request($tenant, 'agent.missing', 'read'));

        self::assertFalse($result->executed);
        self::assertSame('failed', $result->status);
        self::assertSame('workflow_execution_failed', $result->reasonCode);
        self::assertNull($result->workflowRunId);
        self::assertSame('policy_authorized', $result->audit->policyCode);
        $audit = DB::table('dovra_agent_action_audits')->where('id', $result->audit->id)->first();
        self::assertNotNull($audit);
        self::assertSame((string) $tenant->getKey(), (string) $audit->tenant_id);
        self::assertSame('policy_authorized', (string) $audit->policy_code);
        self::assertSame('failed', (string) $audit->status);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('failed', $metadata['workflow_status'] ?? null);
        self::assertSame('workflow_execution_failed', $metadata['workflow_reason_code'] ?? null);
        self::assertStringNotContainsString('Published workflow version not found.', (string) $audit->metadata);
        self::assertSame($metadata, $result->audit->metadata);
        self::assertStringNotContainsString('Published workflow version not found.', json_encode($result->audit->metadata, JSON_THROW_ON_ERROR));
        self::assertSame(0, DB::table('automation_workflow_runs')->where('tenant_id', $tenant->getKey())->count());
        $foreignStateAfter = $this->automationState((string) $foreignTenant->getKey());
        self::assertSame($foreignStateBefore, $foreignStateAfter);
        self::assertSame($foreignHashBefore, $this->automationStateHash($foreignStateAfter));
        self::assertSame($foreignCountsBefore, $this->automationStateCounts($foreignStateAfter));
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

    private function policy(Tenant $tenant, string $actionKey, string $maxRisk, bool $confirmationRequired = false): void
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
            'confirmation_required' => $confirmationRequired,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function request(
        Tenant $tenant,
        string $actionKey,
        string $risk,
        array $input = [],
        ?string $agentVersionId = null,
        ?string $conversationId = null,
    ): AgentActionRequest
    {
        return AgentActionRequest::fromWorkforce(
            (string) $tenant->getKey(),
            $agentVersionId ?? (string) Str::uuid(),
            $conversationId ?? (string) Str::uuid(),
            $actionKey,
            $input,
            'agent-action:'.$actionKey.':'.Str::uuid(),
            $risk,
        );
    }

    private function malformedRequest(
        Tenant $tenant,
        string $actionKey,
        string $risk,
        array $input,
        string $agentVersionId,
        string $conversationId,
    ): AgentActionRequest {
        $valid = $this->request(
            tenant: $tenant,
            actionKey: $actionKey,
            risk: $risk,
            input: $input,
            agentVersionId: $agentVersionId,
            conversationId: (string) Str::uuid(),
        );
        $reflection = new \ReflectionClass(AgentActionRequest::class);
        /** @var AgentActionRequest $malformed */
        $malformed = $reflection->newInstanceWithoutConstructor();
        foreach ([
            'tenantId',
            'agentVersionId',
            'conversationId',
            'actionKey',
            'input',
            'idempotencyKey',
            'risk',
            'payloadHash',
            'workflowVersion',
            'confirmationId',
            'confirmationToken',
        ] as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $property->setValue(
                $malformed,
                $propertyName === 'conversationId' ? $conversationId : $valid->{$propertyName},
            );
        }

        return $malformed;
    }

    private function privateConversation(Tenant $tenant, User $actor, string $conversationId): void
    {
        $this->conversationRow(
            tenant: $tenant,
            conversationId: $conversationId,
            visibility: 'tenant_private',
            status: 'active',
            actorId: (string) $actor->getKey(),
        );
    }

    private function conversationRow(
        Tenant $tenant,
        string $conversationId,
        string $visibility = 'tenant_private',
        string $status = 'active',
        ?string $actorId = null,
    ): void {
        DB::table('ai_conversations')->insert([
            'id' => $conversationId,
            'tenant_id' => $tenant->getKey(),
            'visibility' => $visibility,
            'origin_type' => 'web',
            'external_thread_hash' => null,
            'actor_id' => $actorId,
            'agent_version_id' => null,
            'locale' => 'es-HN',
            'market_country_code' => 'HN',
            'currency' => 'HNL',
            'timezone' => 'America/Tegucigalpa',
            'status' => $status,
            'retention_category' => 'conversation_standard',
            'retention_expires_at' => now()->addDays(30),
            'last_message_sequence' => 0,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
    }

    private function completedConfirmation(
        Tenant $tenant,
        User $actor,
        AgentActionRequest $request,
        string $confirmationId,
        string $token,
    ): void {
        $context = app(TenantContext::class);
        $activeTenantId = $context->isInitialized() && ! $context->isRoot()
            ? $context->id()
            : null;
        if ($activeTenantId !== (string) $tenant->getKey()) {
            $context->reset();
            $context->setTenant((string) $tenant->getKey());
        }

        try {
            AiToolConfirmation::unguarded(static function () use (
                $tenant,
                $actor,
                $request,
                $confirmationId,
                $token,
            ): void {
                AiToolConfirmation::query()->create([
                    'id' => $confirmationId,
                    'tenant_id' => $tenant->getKey(),
                    'actor_id' => $actor->getKey(),
                    'tool' => $request->actionKey,
                    'payload' => $request->input,
                    'payload_hash' => $request->payloadHash,
                    'confirmation_token' => $token,
                    'confirmation_token_hash' => hash('sha256', $token),
                    'idempotency_key' => 'confirmation-fixture-'.$confirmationId,
                    'status' => AiToolConfirmation::STATUS_COMPLETED,
                    'result' => [],
                    'expires_at' => now()->addHour(),
                    'executed_at' => now()->subMinute(),
                    'cancelled_at' => null,
                    'created_at' => now()->subMinute(),
                    'updated_at' => now()->subMinute(),
                ]);
            });
        } finally {
            if ($activeTenantId !== null) {
                $context->reset();
                $context->setTenant($activeTenantId);
            } elseif ($context->isInitialized()) {
                $context->reset();
            }
        }
    }

    private function membership(Tenant $tenant, User $user, string $role): string
    {
        $id = (string) Str::uuid();
        DB::table('tenant_memberships')->insert([
            'id' => $id,
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'preset_key' => null,
            'granted_permissions' => null,
            'status' => 'active',
            'invited_at' => now()->subDay(),
            'accepted_at' => now()->subDay(),
            'suspended_at' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @return array<string, mixed> */
    private function foreignSentinel(Tenant $tenant, User $actor, string $label): array
    {
        $suffix = preg_replace('/[^a-z0-9]+/', '-', strtolower($label)) ?: 'sentinel';
        $conversationId = (string) Str::uuid();
        $confirmationId = (string) Str::uuid();
        $token = 'foreign-sentinel-token-'.$suffix;
        $actionKey = 'agent.foreign.sentinel.'.$suffix;
        $this->privateConversation($tenant, $actor, $conversationId);
        $request = $this->request(
            tenant: $tenant,
            actionKey: $actionKey,
            risk: 'read',
            input: ['foreign_sentinel' => $suffix],
            agentVersionId: (string) Str::uuid(),
            conversationId: $conversationId,
        );
        $this->completedConfirmation($tenant, $actor, $request, $confirmationId, $token);
        DB::table('dovra_agent_action_audits')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'agent_version_id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'action_key' => $actionKey,
            'idempotency_key' => 'foreign-agent-action-'.$suffix,
            'input_hash' => hash('sha256', 'foreign-sentinel-'.$suffix),
            'risk' => 'read',
            'policy_code' => 'policy_authorized',
            'confirmation_id' => $confirmationId,
            'workflow_run_id' => null,
            'outcome_id' => null,
            'status' => 'authorized',
            'metadata' => json_encode(['foreign_sentinel' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $automationBefore = $this->automationState((string) $tenant->getKey());

        return [
            'tenantId' => (string) $tenant->getKey(),
            'conversationId' => $conversationId,
            'confirmationId' => $confirmationId,
            'conversationBefore' => (array) DB::table('ai_conversations')
                ->where('tenant_id', $tenant->getKey())
                ->where('id', $conversationId)
                ->first(),
            'confirmationBefore' => (array) DB::table('ai_tool_confirmations')
                ->where('tenant_id', $tenant->getKey())
                ->where('id', $confirmationId)
                ->first(),
            'automationBefore' => $automationBefore,
            'automationHashBefore' => $this->automationStateHash($automationBefore),
            'automationCountsBefore' => $this->automationStateCounts($automationBefore),
        ];
    }

    /** @param list<string> $leakedSecrets */
    private function assertBindingDenialInvariants(
        Tenant $tenant,
        AgentActionRequest $request,
        AgentActionResult $result,
        array $confirmationBefore,
        ?array $conversationBefore,
        array $protectedBefore,
        array $counterBefore,
        array $foreignSentinel,
        array $leakedSecrets = [],
    ): void {
        self::assertFalse($result->executed);
        self::assertSame('blocked', $result->status);
        self::assertSame('confirmation_binding_mismatch', $result->reasonCode);
        self::assertSame('confirmation_binding_mismatch', $result->audit->policyCode);
        self::assertSame((string) $tenant->getKey(), $result->audit->tenantId);
        self::assertSame($request->confirmationId, $result->audit->confirmationId);

        $auditRows = DB::table('dovra_agent_action_audits')
            ->where('tenant_id', $tenant->getKey())
            ->where('action_key', $request->actionKey)
            ->where('idempotency_key', $request->idempotencyKey)
            ->get();
        self::assertCount(1, $auditRows);
        $audit = $auditRows->first();
        self::assertNotNull($audit);
        self::assertSame('confirmation_binding_mismatch', (string) $audit->policy_code);
        self::assertSame('blocked', (string) $audit->status);
        self::assertSame((string) $request->confirmationId, (string) $audit->confirmation_id);
        $metadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($metadata);
        foreach ($leakedSecrets as $secret) {
            if ($secret === '') {
                continue;
            }
            self::assertStringNotContainsString($secret, json_encode($metadata, JSON_THROW_ON_ERROR));
        }

        self::assertSame($protectedBefore, $this->protectedEffectState((string) $tenant->getKey()));
        self::assertSame($counterBefore, $this->handlerProviderCounterState());
        self::assertSame(
            $confirmationBefore,
            (array) DB::table('ai_tool_confirmations')
                ->where('tenant_id', $tenant->getKey())
                ->where('id', $request->confirmationId)
                ->first(),
        );
        $conversationAfterRow = DB::table('ai_conversations')
            ->where('tenant_id', $tenant->getKey())
            ->where('id', $request->conversationId)
            ->first();
        self::assertSame($conversationBefore, $conversationAfterRow === null ? null : (array) $conversationAfterRow);

        $foreignStateAfter = $this->automationState($foreignSentinel['tenantId']);
        self::assertSame($foreignSentinel['automationBefore'], $foreignStateAfter);
        self::assertSame($foreignSentinel['automationHashBefore'], $this->automationStateHash($foreignStateAfter));
        self::assertSame($foreignSentinel['automationCountsBefore'], $this->automationStateCounts($foreignStateAfter));
        self::assertSame(
            $foreignSentinel['conversationBefore'],
            (array) DB::table('ai_conversations')
                ->where('tenant_id', $foreignSentinel['tenantId'])
                ->where('id', $foreignSentinel['conversationId'])
                ->first(),
        );
        self::assertSame(
            $foreignSentinel['confirmationBefore'],
            (array) DB::table('ai_tool_confirmations')
                ->where('tenant_id', $foreignSentinel['tenantId'])
                ->where('id', $foreignSentinel['confirmationId'])
                ->first(),
        );
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function protectedEffectState(string $tenantId): array
    {
        return $this->tableState([
            'automation_tool_policies',
            'automation_workflow_definitions',
            'automation_workflow_versions',
            'automation_workflow_runs',
            'automation_workflow_steps',
            'automation_workflow_step_attempts',
            'automation_integration_idempotency',
            'dovra_agent_outcomes',
            'outbox_messages',
            'domain_outbox',
            'domain_outbox_messages',
            'stored_events',
            'processed_events',
            'payments',
            'subscription_payments',
            'orders',
            'order_items',
            'order_events',
            'appointments',
            'leads',
            'products',
            'channel_connections',
            'channel_capability_snapshots',
            'channel_provider_health',
            'channel_identities',
            'channel_consents',
            'channel_delivery_attempts',
            'channel_delivery_outbox',
            'channel_webhook_claims',
            'dovra_conversation_channel_links',
            'dovra_privacy_request_events',
            'global_billing_lifecycle_events',
        ], $tenantId);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function handlerProviderCounterState(): array
    {
        return $this->tableState([
            'automation_tool_invocations',
            'automation_provider_calls',
            'provider_calls',
            'handler_calls',
            'tool_calls',
            'ai_provider_calls',
        ]);
    }

    /**
     * @param list<string> $tables
     * @return array<string, list<array<string, mixed>>>
     */
    private function tableState(array $tables, ?string $tenantId = null): array
    {
        $state = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = Schema::getColumnListing($table);
            $query = DB::table($table);
            if ($tenantId !== null && in_array('tenant_id', $columns, true)) {
                $query->where('tenant_id', $tenantId);
            }
            $orderBy = in_array('id', $columns, true)
                ? 'id'
                : (in_array('created_at', $columns, true) ? 'created_at' : null);
            if ($orderBy !== null) {
                $query->orderBy($orderBy);
            }
            $state[$table] = $query->get()
                ->map(static fn (object $row): array => (array) $row)
                ->values()
                ->all();
        }

        return $state;
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
     *  @return array<string, int>
     */
    private function automationStateCounts(array $state): array
    {
        return array_map(static fn (array $rows): int => count($rows), $state);
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
