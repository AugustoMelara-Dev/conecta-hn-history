<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Application\Automation\ToolPolicyEngine;
use App\Application\Automation\WorkflowDefinitionService;
use App\Application\Automation\WorkflowRuntime;
use App\Domain\Automation\WorkflowResumeRequest;
use App\Domain\Automation\WorkflowStartRequest;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class WorkflowRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_workflow_versions_are_immutable(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $definitions = $this->definitions();

        $draft = $definitions->createDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'fulfillment.follow-up',
            definition: ['steps' => [['id' => 'noop', 'type' => 'tool', 'action' => 'noop']]],
            actorId: (string) $actor->getKey(),
        );

        $published = $definitions->publishDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'fulfillment.follow-up',
            draftVersionId: $draft['version_id'],
            publishedVersion: 'v1',
            actorId: (string) $actor->getKey(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Published workflow versions are immutable.');

        $definitions->updateDraftDefinition(
            tenantId: (string) $tenant->getKey(),
            versionId: $published['version_id'],
            definition: ['steps' => [['id' => 'changed', 'type' => 'tool', 'action' => 'noop']]],
        );
    }

    public function test_runtime_persists_durable_waits_and_resumes_after_the_due_time(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $definitions = $this->definitions();
        $draft = $definitions->createDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'lead.nurture',
            definition: [
                'steps' => [
                    ['id' => 'prepare', 'type' => 'tool', 'action' => 'noop'],
                    ['id' => 'wait-before-followup', 'type' => 'wait', 'delay_seconds' => 300],
                    ['id' => 'followup', 'type' => 'tool', 'action' => 'noop'],
                ],
            ],
            actorId: (string) $actor->getKey(),
        );
        $definitions->publishDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'lead.nurture',
            draftVersionId: $draft['version_id'],
            publishedVersion: 'v1',
            actorId: (string) $actor->getKey(),
        );

        $startedAt = CarbonImmutable::parse('2026-08-02T10:00:00+00:00');
        $runtime = $this->runtime(['noop' => static fn (): array => ['output' => ['ok' => true], 'duration_seconds' => 1]]);

        $started = $runtime->start(new WorkflowStartRequest(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'lead.nurture',
            workflowVersion: 'v1',
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            idempotencyKey: 'lead-nurture-001',
            trigger: 'conversation',
            payload: ['conversation_id' => 'conv-1'],
            confirmations: [],
            requestedAt: $startedAt,
        ));

        self::assertSame('waiting', $started->status);
        self::assertSame('wait-before-followup', $started->currentStepId);
        self::assertSame('2026-08-02T10:05:00+00:00', $started->waitingUntil?->toIso8601String());

        $this->assertDatabaseHas('automation_workflow_runs', [
            'id' => $started->runId,
            'tenant_id' => $tenant->getKey(),
            'status' => 'waiting',
        ]);

        $beforeDue = $runtime->resume($started->runId, new WorkflowResumeRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            confirmations: [],
            requestedAt: $startedAt->addSeconds(299),
        ));

        self::assertSame('waiting', $beforeDue->status);
        self::assertSame('wait-before-followup', $beforeDue->currentStepId);

        $afterDue = $runtime->resume($started->runId, new WorkflowResumeRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            confirmations: [],
            requestedAt: $startedAt->addSeconds(301),
        ));

        self::assertSame('completed', $afterDue->status);
        self::assertNull($afterDue->currentStepId);
        $this->assertDatabaseHas('automation_workflow_runs', [
            'id' => $started->runId,
            'status' => 'completed',
        ]);
        self::assertSame(
            3,
            DB::table('automation_workflow_steps')->where('run_id', $started->runId)->count(),
        );
    }

    public function test_runtime_retries_retryable_failures_and_records_compensation(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $definitions = $this->definitions();
        $draft = $definitions->createDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'payment.capture',
            definition: [
                'steps' => [
                    [
                        'id' => 'reserve',
                        'type' => 'tool',
                        'action' => 'reserve',
                        'compensation_action' => 'release',
                    ],
                    [
                        'id' => 'charge',
                        'type' => 'tool',
                        'action' => 'charge',
                        'retry_limit' => 1,
                        'retry_delay_seconds' => 30,
                    ],
                ],
            ],
            actorId: (string) $actor->getKey(),
        );
        $definitions->publishDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'payment.capture',
            draftVersionId: $draft['version_id'],
            publishedVersion: 'v1',
            actorId: (string) $actor->getKey(),
        );

        $chargeAttempts = 0;
        $compensations = 0;
        $runtime = $this->runtime([
            'reserve' => static fn (): array => ['output' => ['reserved' => true], 'duration_seconds' => 1],
            'charge' => static function () use (&$chargeAttempts): array {
                $chargeAttempts++;

                throw new RuntimeException('retryable:provider_unavailable');
            },
            'release' => static function () use (&$compensations): array {
                $compensations++;

                return ['output' => ['released' => true], 'duration_seconds' => 1];
            },
        ]);

        $startedAt = CarbonImmutable::parse('2026-08-02T12:00:00+00:00');
        $started = $runtime->start(new WorkflowStartRequest(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'payment.capture',
            workflowVersion: 'v1',
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            idempotencyKey: 'payment-capture-001',
            trigger: 'webhook',
            payload: ['payment_id' => 'pay-1'],
            confirmations: [],
            requestedAt: $startedAt,
        ));

        self::assertSame('waiting', $started->status);
        self::assertSame('charge', $started->currentStepId);
        self::assertSame(1, $chargeAttempts);

        $failed = $runtime->resume($started->runId, new WorkflowResumeRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            confirmations: [],
            requestedAt: $startedAt->addSeconds(31),
        ));

        self::assertSame('failed', $failed->status);
        self::assertSame(2, $chargeAttempts);
        self::assertSame(1, $compensations);
        $this->assertDatabaseHas('automation_workflow_step_attempts', [
            'run_id' => $started->runId,
            'step_key' => 'charge',
            'attempt_number' => 1,
            'attempt_kind' => 'tool',
            'status' => 'waiting',
        ]);
        $this->assertDatabaseHas('automation_workflow_step_attempts', [
            'run_id' => $started->runId,
            'step_key' => 'charge',
            'attempt_number' => 2,
            'attempt_kind' => 'tool',
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('automation_workflow_step_attempts', [
            'run_id' => $started->runId,
            'step_key' => 'reserve',
            'attempt_number' => 1,
            'attempt_kind' => 'compensation',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('automation_workflow_steps', [
            'run_id' => $started->runId,
            'step_key' => 'reserve',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('automation_workflow_steps', [
            'run_id' => $started->runId,
            'step_key' => 'charge',
            'status' => 'failed',
            'attempts' => 2,
        ]);
        $this->assertDatabaseHas('automation_workflow_steps', [
            'run_id' => $started->runId,
            'step_key' => 'reserve:compensation',
            'status' => 'completed',
        ]);
    }

    public function test_resume_fails_closed_for_a_run_from_another_tenant(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        [$foreignTenant, $foreignActor] = $this->tenantActor();
        $definitions = $this->definitions();
        $draft = $definitions->createDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'tenant.boundary',
            definition: [
                'steps' => [['id' => 'pause', 'type' => 'wait', 'delay_seconds' => 300]],
            ],
            actorId: (string) $actor->getKey(),
        );
        $definitions->publishDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'tenant.boundary',
            draftVersionId: $draft['version_id'],
            publishedVersion: 'v1',
            actorId: (string) $actor->getKey(),
        );

        $runtime = $this->runtime([]);
        $started = $runtime->start(new WorkflowStartRequest(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'tenant.boundary',
            workflowVersion: 'v1',
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            idempotencyKey: 'tenant-boundary-001',
            trigger: 'conversation',
            payload: [],
            confirmations: [],
            requestedAt: CarbonImmutable::parse('2026-08-02T16:00:00+00:00'),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Workflow run tenant authorization failed.');

        $runtime->resume($started->runId, new WorkflowResumeRequest(
            tenantId: (string) $foreignTenant->getKey(),
            actorId: (string) $foreignActor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            confirmations: [],
            requestedAt: CarbonImmutable::parse('2026-08-02T16:01:00+00:00'),
        ));
    }

    public function test_resume_fails_closed_for_an_actor_that_does_not_own_the_run(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        [, $foreignActor] = $this->tenantActor();
        $definitions = $this->definitions();
        $draft = $definitions->createDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'actor.boundary',
            definition: [
                'steps' => [['id' => 'pause', 'type' => 'wait', 'delay_seconds' => 300]],
            ],
            actorId: (string) $actor->getKey(),
        );
        $definitions->publishDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'actor.boundary',
            draftVersionId: $draft['version_id'],
            publishedVersion: 'v1',
            actorId: (string) $actor->getKey(),
        );

        $runtime = $this->runtime([]);
        $started = $runtime->start(new WorkflowStartRequest(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'actor.boundary',
            workflowVersion: 'v1',
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            idempotencyKey: 'actor-boundary-001',
            trigger: 'conversation',
            payload: [],
            confirmations: [],
            requestedAt: CarbonImmutable::parse('2026-08-02T17:00:00+00:00'),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Workflow run actor authorization failed.');

        $runtime->resume($started->runId, new WorkflowResumeRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $foreignActor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            confirmations: [],
            requestedAt: CarbonImmutable::parse('2026-08-02T17:01:00+00:00'),
        ));
    }

    public function test_waiting_tool_retry_creates_a_new_authorized_attempt_with_a_stable_provider_tool_key(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $definitions = $this->definitions();
        $draft = $definitions->createDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'provider.retry',
            definition: [
                'steps' => [[
                    'id' => 'capture',
                    'type' => 'tool',
                    'action' => 'capture',
                    'retry_limit' => 1,
                    'retry_delay_seconds' => 30,
                    'authorization' => [
                        'provider' => 'acme-payments',
                        'tool' => 'payments.capture',
                        'tool_version' => '2026-08-01',
                        'risk' => 'prepare',
                        'payload' => ['payment_id' => 'pay-2'],
                    ],
                ]],
            ],
            actorId: (string) $actor->getKey(),
        );
        $definitions->publishDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'provider.retry',
            draftVersionId: $draft['version_id'],
            publishedVersion: 'v1',
            actorId: (string) $actor->getKey(),
        );

        DB::table('automation_tool_policies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'tool' => 'payments.capture',
            'tool_version' => '2026-08-01',
            'allowed_roles' => json_encode(['owner'], JSON_THROW_ON_ERROR),
            'allowed_plans' => json_encode(['pro'], JSON_THROW_ON_ERROR),
            'allowed_channels' => json_encode(['api'], JSON_THROW_ON_ERROR),
            'max_risk' => 'prepare',
            'confirmation_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $handlerCalls = 0;
        $runtime = $this->runtime([
            'capture' => static function () use (&$handlerCalls): array {
                $handlerCalls++;
                if ($handlerCalls === 1) {
                    throw new RuntimeException('retryable:provider_unavailable');
                }

                return ['output' => ['captured' => true], 'duration_seconds' => 1];
            },
        ]);

        $startedAt = CarbonImmutable::parse('2026-08-02T19:00:00+00:00');
        $started = $runtime->start(new WorkflowStartRequest(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'provider.retry',
            workflowVersion: 'v1',
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            idempotencyKey: 'provider-retry-001',
            trigger: 'schedule',
            payload: ['payment_id' => 'pay-2'],
            confirmations: [],
            requestedAt: $startedAt,
        ));

        self::assertSame('waiting', $started->status);

        $resumed = $runtime->resume($started->runId, new WorkflowResumeRequest(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            confirmations: [],
            requestedAt: $startedAt->addSeconds(31),
        ));

        self::assertSame('completed', $resumed->status);
        self::assertSame(2, $handlerCalls);
        self::assertSame(2, DB::table('automation_workflow_step_attempts')
            ->where('run_id', $started->runId)
            ->where('step_key', 'capture')
            ->where('attempt_kind', 'tool')
            ->count());
        $attempts = DB::table('automation_workflow_step_attempts')
            ->where('run_id', $started->runId)
            ->where('step_key', 'capture')
            ->where('attempt_kind', 'tool')
            ->orderBy('attempt_number')
            ->get();
        self::assertSame('acme-payments:payments.capture', $attempts[0]->provider_tool_key);
        self::assertSame('acme-payments:payments.capture', $attempts[1]->provider_tool_key);
        self::assertNotSame($attempts[0]->idempotency_key, $attempts[1]->idempotency_key);
        self::assertSame(2, DB::table('automation_integration_idempotency')
            ->where('tenant_id', $tenant->getKey())
            ->where('scope', 'tool:acme-payments:payments.capture')
            ->count());
    }

    public function test_runtime_treats_tool_timeouts_as_terminal_when_retry_budget_is_zero(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $definitions = $this->definitions();
        $draft = $definitions->createDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'inventory.reconcile',
            definition: [
                'steps' => [[
                    'id' => 'slow-tool',
                    'type' => 'tool',
                    'action' => 'slow',
                    'timeout_seconds' => 5,
                    'retry_limit' => 0,
                ]],
            ],
            actorId: (string) $actor->getKey(),
        );
        $definitions->publishDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'inventory.reconcile',
            draftVersionId: $draft['version_id'],
            publishedVersion: 'v1',
            actorId: (string) $actor->getKey(),
        );

        $runtime = $this->runtime([
            'slow' => static fn (): array => ['output' => ['ok' => true], 'duration_seconds' => 10],
        ]);

        $result = $runtime->start(new WorkflowStartRequest(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'inventory.reconcile',
            workflowVersion: 'v1',
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            idempotencyKey: 'inventory-reconcile-001',
            trigger: 'schedule',
            payload: ['schedule' => 'daily'],
            confirmations: [],
            requestedAt: CarbonImmutable::parse('2026-08-02T15:00:00+00:00'),
        ));

        self::assertSame('failed', $result->status);
        $this->assertDatabaseHas('automation_workflow_steps', [
            'run_id' => $result->runId,
            'step_key' => 'slow-tool',
            'status' => 'failed',
            'last_error_code' => 'tool_timeout',
        ]);
    }

    private function definitions(): WorkflowDefinitionService
    {
        return new WorkflowDefinitionService(new DatabaseWorkflowRepository(DB::connection()));
    }

    /** @param  array<string, callable>  $handlers */
    private function runtime(array $handlers): WorkflowRuntime
    {
        return new WorkflowRuntime(
            definitions: $this->definitions(),
            runs: new DatabaseWorkflowRepository(DB::connection()),
            policyEngine: new ToolPolicyEngine(new DatabaseWorkflowRepository(DB::connection())),
            toolHandlers: $handlers,
        );
    }

    /** @return array{Tenant, User} */
    private function tenantActor(): array
    {
        /** @var array{Tenant, User} $result */
        $result = Model::withoutEvents(function (): array {
            $actor = User::factory()->create();
            $tenant = Tenant::factory()->forUser($actor)->pro()->create();
            $actor->forceFill(['tenant_id' => $tenant->getKey()])->save();

            return [$tenant, $actor];
        });

        return $result;
    }
}
