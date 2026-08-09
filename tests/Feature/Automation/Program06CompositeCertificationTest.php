<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Application\Automation\AgentAction\AgentActionAudit;
use App\Application\Automation\AgentAction\AgentActionExecutor;
use App\Application\Automation\AgentAction\AgentActionRequest;
use App\Application\Automation\AgentAction\AgentActionResult;
use App\Application\Automation\AgentAction\AgentOutcomeRecorder;
use App\Application\Automation\AutomationContractService;
use App\Application\Automation\ToolPolicyEngine;
use App\Application\Automation\WorkflowDefinitionService;
use App\Application\Automation\WorkflowRuntime;
use App\Domain\Automation\ToolAuthorizationRequest;
use App\Domain\Automation\WorkflowResumeRequest;
use App\Domain\Automation\WorkflowStartRequest;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\AI\Tools\AiToolConfirmationService;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use App\Infrastructure\Shared\Persistence\EloquentOutboxRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\Support\Automation\Program06CompositeCertificateFixture;
use Tests\Support\Automation\Program06CompositeCertificateReport;
use Tests\TestCase;

final class Program06CompositeCertificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (strtolower((string) getenv('DOVRA_PROGRAM06_COMPOSITE_ALLOW')) !== 'true') {
            $this->markTestSkipped('Program 06 composite requires its explicit disposable-SQLite certifier guard.');
        }

        parent::setUp();
    }

    public function test_existing_program06_seams_compose_locally_without_claiming_production_authority(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('sqlite', (string) config('database.default'));
        self::assertSame('true', strtolower((string) getenv('DOVRA_PROGRAM06_COMPOSITE_ALLOW')));
        self::assertSame('sqlite', (string) getenv('DB_CONNECTION'));

        $fixture = Program06CompositeCertificateFixture::create();
        $subjectTenant = (string) $fixture['subject_tenant'];
        $primaryActor = (string) $fixture['primary_actor'];
        $sameTenantActor = (string) $fixture['same_tenant_actor'];
        $foreignTenant = (string) $fixture['foreign_tenant'];
        $foreignActor = (string) $fixture['foreign_actor'];
        self::assertNotSame($primaryActor, $sameTenantActor);

        $repository = new DatabaseWorkflowRepository(DB::connection());
        $definitions = new WorkflowDefinitionService($repository);
        $policyEngine = new ToolPolicyEngine($repository);

        $foreignBaseline = Program06CompositeCertificateFixture::tenantState($foreignTenant);
        self::assertGreaterThan(0, (int) $foreignBaseline['row_count']);
        $baselineSideEffects = $this->sideEffectCounts();

        // 1. Guard and published-definition authority.
        $published = $definitions->publishedVersion(
            $subjectTenant,
            (string) $fixture['workflow_key'],
            (string) $fixture['workflow_version'],
        );
        self::assertNotNull($published);
        self::assertSame('published', (string) ($published['status'] ?? ''));
        self::assertSame('v1', (string) ($published['version'] ?? ''));
        $definitionBefore = Program06CompositeCertificateFixture::hashState((array) ($published['definition'] ?? []));
        $immutable = false;
        try {
            $definitions->updateDraftDefinition(
                $subjectTenant,
                (string) $published['id'],
                ['steps' => [['id' => 'mutated', 'type' => 'tool', 'action' => 'certificate.local.read']]],
            );
        } catch (RuntimeException $exception) {
            $immutable = $exception->getMessage() === 'Published workflow versions are immutable.';
        }
        self::assertTrue($immutable);
        $publishedAfter = $definitions->publishedVersion(
            $subjectTenant,
            (string) $fixture['workflow_key'],
            (string) $fixture['workflow_version'],
        );
        self::assertNotNull($publishedAfter);
        self::assertSame($definitionBefore, Program06CompositeCertificateFixture::hashState((array) ($publishedAfter['definition'] ?? [])));
        self::assertSame($foreignBaseline, Program06CompositeCertificateFixture::tenantState($foreignTenant));

        // 2. Deny-by-default policy and policy-level confirmation binding.
        $unknownBefore = DB::table('automation_integration_idempotency')
            ->where('tenant_id', $subjectTenant)
            ->count();
        $unknown = $policyEngine->evaluate(new ToolAuthorizationRequest(
            tenantId: $subjectTenant,
            actorId: $primaryActor,
            actorRoles: ['primary'],
            plan: 'pro',
            channel: 'api',
            tool: 'certificate.unknown.mutation',
            toolVersion: '2026-08-01',
            risk: 'mutation',
            payloadHash: hash('sha256', 'unknown'),
            idempotencyKey: 'program06-unknown-mutation',
            confirmationId: null,
            confirmationToken: null,
            provider: 'local',
        ));
        self::assertFalse($unknown->allowed);
        self::assertSame('policy_missing', $unknown->code);
        self::assertSame($unknownBefore, DB::table('automation_integration_idempotency')->where('tenant_id', $subjectTenant)->count());

        $readEvaluation = $policyEngine->evaluate(new ToolAuthorizationRequest(
            tenantId: $subjectTenant,
            actorId: $primaryActor,
            actorRoles: ['agent'],
            plan: 'pro',
            channel: 'workforce',
            tool: 'certificate.local.read',
            toolVersion: 'agent-action-v1',
            risk: 'read',
            payloadHash: hash('sha256', '{"read":true}'),
            idempotencyKey: 'program06-policy-read',
            confirmationId: null,
            confirmationToken: null,
            provider: 'workflow',
        ));
        self::assertTrue($readEvaluation->allowed);
        self::assertSame('policy_authorized', $readEvaluation->code);
        self::assertFalse((bool) ($readEvaluation->metadata['confirmation_required'] ?? true));
        self::assertSame($unknownBefore, DB::table('automation_integration_idempotency')->where('tenant_id', $subjectTenant)->count());

        $foreignActorPolicySubjectBefore = Program06CompositeCertificateFixture::tenantState($subjectTenant);
        $foreignActorPolicyForeignBefore = Program06CompositeCertificateFixture::tenantState($foreignTenant);
        $foreignActorPolicy = $policyEngine->evaluate(new ToolAuthorizationRequest(
            tenantId: $subjectTenant,
            actorId: $foreignActor,
            actorRoles: ['foreign'],
            plan: 'pro',
            channel: 'workforce',
            tool: 'certificate.local.read',
            toolVersion: 'agent-action-v1',
            risk: 'read',
            payloadHash: hash('sha256', '{"read":true}'),
            idempotencyKey: 'program06-foreign-actor-policy',
            confirmationId: null,
            confirmationToken: null,
            provider: 'workflow',
        ));
        self::assertFalse($foreignActorPolicy->allowed);
        self::assertSame('role_denied', $foreignActorPolicy->code);
        self::assertSame($unknownBefore, DB::table('automation_integration_idempotency')->where('tenant_id', $subjectTenant)->count());
        self::assertSame($foreignActorPolicySubjectBefore, Program06CompositeCertificateFixture::tenantState($subjectTenant));
        self::assertSame($foreignActorPolicyForeignBefore, Program06CompositeCertificateFixture::tenantState($foreignTenant));
        $foreignActorAttempted = true;

        $confirmationId = (string) Str::uuid();
        $confirmationToken = 'program06-confirmation-token';
        $confirmationPayloadHash = hash('sha256', 'program06-confirmation-payload');
        DB::table('automation_confirmation_attempts')->insert([
            'id' => $confirmationId,
            'tenant_id' => $subjectTenant,
            'actor_id' => $primaryActor,
            'tool' => 'certificate.confirmed',
            'tool_version' => '2026-08-01',
            'payload_hash' => $confirmationPayloadHash,
            'confirmation_token_hash' => hash('sha256', $confirmationToken),
            'status' => 'pending',
            'encrypted_payload' => null,
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
            'consumed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mismatchedConfirmation = $policyEngine->authorize(new ToolAuthorizationRequest(
            tenantId: $subjectTenant,
            actorId: $sameTenantActor,
            actorRoles: ['primary'],
            plan: 'pro',
            channel: 'api',
            tool: 'certificate.confirmed',
            toolVersion: '2026-08-01',
            risk: 'mutation',
            payloadHash: $confirmationPayloadHash,
            idempotencyKey: 'program06-confirmation-mismatch',
            confirmationId: $confirmationId,
            confirmationToken: $confirmationToken,
            provider: 'local',
        ));
        self::assertFalse($mismatchedConfirmation->allowed);
        self::assertSame('confirmation_binding_mismatch', $mismatchedConfirmation->code);
        self::assertSame(0, DB::table('automation_integration_idempotency')->where('tenant_id', $subjectTenant)->where('scope', 'tool:local:certificate.confirmed')->count());

        $authorizedConfirmation = $policyEngine->authorize(new ToolAuthorizationRequest(
            tenantId: $subjectTenant,
            actorId: $primaryActor,
            actorRoles: ['primary'],
            plan: 'pro',
            channel: 'api',
            tool: 'certificate.confirmed',
            toolVersion: '2026-08-01',
            risk: 'mutation',
            payloadHash: $confirmationPayloadHash,
            idempotencyKey: 'program06-confirmation-authorized',
            confirmationId: $confirmationId,
            confirmationToken: $confirmationToken,
            provider: 'local',
        ));
        self::assertTrue($authorizedConfirmation->allowed);
        self::assertSame('authorized', $authorizedConfirmation->code);
        self::assertSame('consumed', (string) DB::table('automation_confirmation_attempts')->where('id', $confirmationId)->value('status'));
        self::assertSame(1, DB::table('automation_integration_idempotency')->where('tenant_id', $subjectTenant)->where('scope', 'tool:local:certificate.confirmed')->count());
        $confirmationReplay = $policyEngine->authorize(new ToolAuthorizationRequest(
            tenantId: $subjectTenant,
            actorId: $primaryActor,
            actorRoles: ['primary'],
            plan: 'pro',
            channel: 'api',
            tool: 'certificate.confirmed',
            toolVersion: '2026-08-01',
            risk: 'mutation',
            payloadHash: $confirmationPayloadHash,
            idempotencyKey: 'program06-confirmation-authorized',
            confirmationId: $confirmationId,
            confirmationToken: $confirmationToken,
            provider: 'local',
        ));
        self::assertFalse($confirmationReplay->allowed);
        self::assertSame('idempotency_replay', $confirmationReplay->code);
        self::assertSame($foreignBaseline, Program06CompositeCertificateFixture::tenantState($foreignTenant));

        // 3. Workforce read action and exact replay.
        $context = app(TenantContext::class);
        $context->reset();
        $context->setTenant($subjectTenant);
        $actionRequest = AgentActionRequest::fromWorkforce(
            tenantId: $subjectTenant,
            agentVersionId: $primaryActor,
            conversationId: '11111111-1111-4111-8111-111111111111',
            actionKey: 'certificate.local.read',
            input: ['subject' => 'program06-local'],
            idempotencyKey: 'program06-action-001',
            risk: 'read',
        );
        $actionRuntime = new WorkflowRuntime(
            definitions: $definitions,
            runs: $repository,
            policyEngine: $policyEngine,
            toolHandlers: [
                'certificate.local.read' => static fn (): array => ['output' => ['local' => true], 'duration_seconds' => 1],
            ],
        );
        $executor = new AgentActionExecutor(
            policyEngine: $policyEngine,
            confirmations: app(AiToolConfirmationService::class),
            workflowRuntime: $actionRuntime,
            repository: $repository,
            tenantContext: $context,
        );
        $actionResult = $executor->execute($actionRequest);
        self::assertTrue($actionResult->executed);
        self::assertSame('completed', $actionResult->status);
        self::assertSame('completed', $actionResult->reasonCode);
        self::assertNotNull($actionResult->workflowRunId);
        $actionAuditCount = DB::table('dovra_agent_action_audits')->where('tenant_id', $subjectTenant)->where('action_key', 'certificate.local.read')->count();
        $actionRunCount = DB::table('automation_workflow_runs')->where('tenant_id', $subjectTenant)->where('workflow_key', 'certificate.local.read')->count();
        self::assertSame(1, $actionAuditCount);
        self::assertSame(1, $actionRunCount);
        $actionAfterFirst = Program06CompositeCertificateFixture::tenantState($subjectTenant);
        $actionReplay = $executor->execute($actionRequest);
        self::assertFalse($actionReplay->executed);
        self::assertSame('duplicate', $actionReplay->status);
        self::assertSame('idempotency_replay', $actionReplay->reasonCode);
        self::assertTrue($actionReplay->replayed);
        self::assertSame((string) $actionResult->audit->id, (string) $actionReplay->audit->id);
        self::assertSame((string) $actionResult->workflowRunId, (string) $actionReplay->workflowRunId);
        self::assertSame($actionAfterFirst, Program06CompositeCertificateFixture::tenantState($subjectTenant));
        self::assertSame($foreignBaseline, Program06CompositeCertificateFixture::tenantState($foreignTenant));

        // 4. Durable wait/resume with non-vacuous tenant and actor boundaries.
        $waitRuntime = new WorkflowRuntime(
            definitions: $definitions,
            runs: $repository,
            policyEngine: $policyEngine,
            toolHandlers: [
                'certificate.local.wait-read' => static fn (): array => ['output' => ['resumed' => true], 'duration_seconds' => 1],
            ],
        );
        $waitAt = CarbonImmutable::parse('2026-08-03T10:00:00+00:00');
        $waitRequest = new WorkflowStartRequest(
            tenantId: $subjectTenant,
            workflowKey: 'certificate.local.wait',
            workflowVersion: 'v1',
            actorId: $primaryActor,
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            idempotencyKey: 'program06-wait-001',
            trigger: 'conversation',
            payload: ['purpose' => 'program06-wait'],
            requestedAt: $waitAt,
        );
        $waitStarted = $waitRuntime->start($waitRequest);
        self::assertSame('waiting', $waitStarted->status);
        self::assertSame('pause', $waitStarted->currentStepId);
        self::assertNotNull($waitStarted->waitingUntil);
        $waitRunId = $waitStarted->runId;
        $waitBeforeEarly = Program06CompositeCertificateFixture::runState($waitRunId, $subjectTenant);
        $early = $waitRuntime->resume($waitRunId, new WorkflowResumeRequest(
            tenantId: $subjectTenant,
            actorId: $primaryActor,
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            requestedAt: $waitAt->addSeconds(299),
        ));
        self::assertSame('waiting', $early->status);
        self::assertSame($waitBeforeEarly, Program06CompositeCertificateFixture::runState($waitRunId, $subjectTenant));
        $negativeForeignBefore = Program06CompositeCertificateFixture::tenantState($foreignTenant);
        $negativeTenantRunBefore = Program06CompositeCertificateFixture::runState($waitRunId, $subjectTenant);
        $foreignTenantRejected = false;
        try {
            $waitRuntime->resume($waitRunId, new WorkflowResumeRequest(
                tenantId: $foreignTenant,
                actorId: $foreignActor,
                actorRoles: ['owner'],
                plan: 'pro',
                channel: 'api',
                requestedAt: $waitAt->addSeconds(299),
            ));
        } catch (RuntimeException $exception) {
            $foreignTenantRejected = $exception->getMessage() === 'Workflow run tenant authorization failed.';
        }
        self::assertTrue($foreignTenantRejected);
        self::assertSame($negativeForeignBefore, Program06CompositeCertificateFixture::tenantState($foreignTenant));
        self::assertSame($negativeTenantRunBefore, Program06CompositeCertificateFixture::runState($waitRunId, $subjectTenant));
        $sameTenantActorBefore = Program06CompositeCertificateFixture::runState($waitRunId, $subjectTenant);
        $sameTenantForeignBefore = Program06CompositeCertificateFixture::tenantState($foreignTenant);
        $sameTenantActorRejected = false;
        try {
            $waitRuntime->resume($waitRunId, new WorkflowResumeRequest(
                tenantId: $subjectTenant,
                actorId: $sameTenantActor,
                actorRoles: ['owner'],
                plan: 'pro',
                channel: 'api',
                requestedAt: $waitAt->addSeconds(299),
            ));
        } catch (RuntimeException $exception) {
            $sameTenantActorRejected = $exception->getMessage() === 'Workflow run actor authorization failed.';
        }
        self::assertTrue($sameTenantActorRejected);
        self::assertSame($sameTenantActorBefore, Program06CompositeCertificateFixture::runState($waitRunId, $subjectTenant));
        self::assertSame($sameTenantForeignBefore, Program06CompositeCertificateFixture::tenantState($foreignTenant));
        $waitCompleted = $waitRuntime->resume($waitRunId, new WorkflowResumeRequest(
            tenantId: $subjectTenant,
            actorId: $primaryActor,
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            requestedAt: $waitAt->addSeconds(301),
        ));
        self::assertSame('completed', $waitCompleted->status);
        self::assertNull($waitCompleted->currentStepId);
        self::assertSame(1, DB::table('automation_workflow_step_attempts')->where('run_id', $waitRunId)->where('attempt_kind', 'tool')->count());
        $waitReplay = $waitRuntime->start($waitRequest);
        self::assertTrue($waitReplay->replayed);
        self::assertSame('completed', $waitReplay->status);
        self::assertSame($foreignBaseline, Program06CompositeCertificateFixture::tenantState($foreignTenant));

        // 5. Retry and compensation history.
        $chargeCalls = 0;
        $historyRuntime = new WorkflowRuntime(
            definitions: $definitions,
            runs: $repository,
            policyEngine: $policyEngine,
            toolHandlers: [
                'certificate.local.reserve' => static fn (): array => ['output' => ['reserved' => true], 'duration_seconds' => 1],
                'certificate.local.release' => static fn (): array => ['output' => ['released' => true], 'duration_seconds' => 1],
                'certificate.local.charge' => static function () use (&$chargeCalls): array {
                    $chargeCalls++;
                    throw new RuntimeException('retryable:provider_unavailable');
                },
            ],
        );
        $retryAt = CarbonImmutable::parse('2026-08-03T11:00:00+00:00');
        $retryRequest = new WorkflowStartRequest(
            tenantId: $subjectTenant,
            workflowKey: 'certificate.local.retry',
            workflowVersion: 'v1',
            actorId: $primaryActor,
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            idempotencyKey: 'program06-retry-001',
            trigger: 'schedule',
            payload: ['purpose' => 'program06-retry'],
            requestedAt: $retryAt,
        );
        $retryStarted = $historyRuntime->start($retryRequest);
        self::assertSame('waiting', $retryStarted->status);
        $retryTerminal = $historyRuntime->resume($retryStarted->runId, new WorkflowResumeRequest(
            tenantId: $subjectTenant,
            actorId: $primaryActor,
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            requestedAt: $retryAt->addSeconds(31),
        ));
        self::assertSame('failed', $retryTerminal->status);
        self::assertSame(2, $chargeCalls);
        $retryAttemptsBeforeReplay = DB::table('automation_workflow_step_attempts')->where('run_id', $retryStarted->runId)->count();
        $retryReplay = $historyRuntime->resume($retryStarted->runId, new WorkflowResumeRequest(
            tenantId: $subjectTenant,
            actorId: $primaryActor,
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            requestedAt: $retryAt->addSeconds(32),
        ));
        self::assertTrue($retryReplay->replayed);
        self::assertSame('failed', $retryReplay->status);
        self::assertSame($retryAttemptsBeforeReplay, DB::table('automation_workflow_step_attempts')->where('run_id', $retryStarted->runId)->count());
        $chargeAttempts = DB::table('automation_workflow_step_attempts')
            ->where('run_id', $retryStarted->runId)
            ->where('step_key', 'charge')
            ->where('attempt_kind', 'tool')
            ->orderBy('attempt_number')
            ->get();
        self::assertCount(2, $chargeAttempts);
        self::assertSame('local:certificate.local.charge', (string) $chargeAttempts[0]->provider_tool_key);
        self::assertSame((string) $chargeAttempts[0]->provider_tool_key, (string) $chargeAttempts[1]->provider_tool_key);
        self::assertNotSame((string) $chargeAttempts[0]->idempotency_key, (string) $chargeAttempts[1]->idempotency_key);
        $compensationAttempts = DB::table('automation_workflow_step_attempts')
            ->where('run_id', $retryStarted->runId)
            ->where('attempt_kind', 'compensation')
            ->get();
        self::assertCount(1, $compensationAttempts);
        self::assertSame('completed', (string) $compensationAttempts[0]->status);
        self::assertSame($foreignBaseline, Program06CompositeCertificateFixture::tenantState($foreignTenant));

        // 6. API/MCP/webhook contracts, with synthetic capability and routes only in-process.
        $previousProviders = config('automation.providers');
        $previousContracts = config('automation.contracts');
        $contractDefaultFailClosed = false;
        $missingActorFailClosed = false;
        $invalidSignatureSideEffects = 0;
        $syntheticApiReplayRejected = false;
        $syntheticMcpReplayRejected = false;
        $syntheticWebhookReplayRejected = false;
        try {
            config()->set('automation.providers', []);
            config()->set('automation.contracts', ['api_route_bound' => false, 'mcp_route_bound' => false]);
            $defaultContract = new AutomationContractService($repository);
            $defaultDecision = $defaultContract->authorizeApi(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-platform',
                capability: 'automation.trigger',
                version: '2026-08-01',
                idempotencyKey: 'program06-default-api',
                payload: '{}',
                signature: 'invalid',
            );
            self::assertFalse($defaultDecision->allowed);
            self::assertSame('provider_capability_unavailable', $defaultDecision->code);
            $contractDefaultFailClosed = true;

            config()->set('automation.providers', [
                'synthetic-platform' => [
                    'markets' => [
                        'HN' => [
                            'legal_basis' => 'contracted-test-only',
                            'capabilities' => ['automation.trigger', 'automation.mcp.invoke'],
                            'api_secret' => 'program06-api-secret',
                            'mcp_secret' => 'program06-mcp-secret',
                        ],
                    ],
                ],
                'synthetic-webhook' => [
                    'markets' => [
                        'HN' => [
                            'legal_basis' => 'contracted-test-only',
                            'capabilities' => ['automation.webhook.receive'],
                            'webhook_secret' => 'program06-webhook-secret',
                        ],
                    ],
                ],
            ]);
            $routeUnboundApi = $defaultContract->authorizeApi(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-platform',
                capability: 'automation.trigger',
                version: '2026-08-01',
                idempotencyKey: 'program06-route-unbound-api',
                payload: '{}',
                signature: self::signature('program06-api-secret', $subjectTenant, $primaryActor, 'program06-route-unbound-api', '{}'),
            );
            self::assertFalse($routeUnboundApi->allowed);
            self::assertSame('route_not_bound', $routeUnboundApi->code);
            $routeUnboundMcp = $defaultContract->authorizeMcp(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-platform',
                capability: 'automation.mcp.invoke',
                version: '2026-08-01',
                idempotencyKey: 'program06-route-unbound-mcp',
                payload: '{}',
                signature: self::signature('program06-mcp-secret', $subjectTenant, $primaryActor, 'program06-route-unbound-mcp', '{}'),
            );
            self::assertFalse($routeUnboundMcp->allowed);
            self::assertSame('route_not_bound', $routeUnboundMcp->code);

            config()->set('automation.contracts', ['api_route_bound' => true, 'mcp_route_bound' => true]);
            $contractService = new AutomationContractService($repository);
            $apiPayload = '{"event":"program06"}';
            $apiKey = 'program06-api-001';
            $apiSignature = self::signature('program06-api-secret', $subjectTenant, $primaryActor, $apiKey, $apiPayload);
            $missingMcpKey = 'program06-mcp-missing-actor';
            $missingApiClaimsBefore = DB::table('automation_integration_idempotency')
                ->where('tenant_id', $subjectTenant)
                ->where('scope', 'inbound:api:synthetic-platform:automation.trigger:2026-08-01')
                ->where('idempotency_key', $apiKey)
                ->count();
            $missingMcpClaimsBefore = DB::table('automation_integration_idempotency')
                ->where('tenant_id', $subjectTenant)
                ->where('scope', 'inbound:mcp:synthetic-platform:automation.mcp.invoke:2026-08-01')
                ->where('idempotency_key', $missingMcpKey)
                ->count();
            $missingActor = $contractService->authorizeApi(
                tenantId: $subjectTenant,
                actorId: '',
                market: 'HN',
                provider: 'synthetic-platform',
                capability: 'automation.trigger',
                version: '2026-08-01',
                idempotencyKey: $apiKey,
                payload: $apiPayload,
                signature: $apiSignature,
            );
            self::assertFalse($missingActor->allowed);
            self::assertSame('inbound_context_required', $missingActor->code);
            $missingMcpActor = $contractService->authorizeMcp(
                tenantId: $subjectTenant,
                actorId: '',
                market: 'HN',
                provider: 'synthetic-platform',
                capability: 'automation.mcp.invoke',
                version: '2026-08-01',
                idempotencyKey: $missingMcpKey,
                payload: '{"tool":"program06"}',
                signature: self::signature('program06-mcp-secret', $subjectTenant, $primaryActor, $missingMcpKey, '{"tool":"program06"}'),
            );
            self::assertFalse($missingMcpActor->allowed);
            self::assertSame('inbound_context_required', $missingMcpActor->code);
            self::assertSame($missingApiClaimsBefore, DB::table('automation_integration_idempotency')
                ->where('tenant_id', $subjectTenant)
                ->where('scope', 'inbound:api:synthetic-platform:automation.trigger:2026-08-01')
                ->where('idempotency_key', $apiKey)
                ->count());
            self::assertSame($missingMcpClaimsBefore, DB::table('automation_integration_idempotency')
                ->where('tenant_id', $subjectTenant)
                ->where('scope', 'inbound:mcp:synthetic-platform:automation.mcp.invoke:2026-08-01')
                ->where('idempotency_key', $missingMcpKey)
                ->count());
            $missingActorFailClosed = true;
            $api = $contractService->authorizeApi(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-platform',
                capability: 'automation.trigger',
                version: '2026-08-01',
                idempotencyKey: $apiKey,
                payload: $apiPayload,
                signature: $apiSignature,
            );
            self::assertTrue($api->allowed);
            $apiReplay = $contractService->authorizeApi(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-platform',
                capability: 'automation.trigger',
                version: '2026-08-01',
                idempotencyKey: $apiKey,
                payload: $apiPayload,
                signature: $apiSignature,
            );
            self::assertFalse($apiReplay->allowed);
            self::assertSame('idempotency_replay', $apiReplay->code);
            $syntheticApiReplayRejected = true;

            $mcpPayload = '{"tool":"program06"}';
            $mcpKey = 'program06-mcp-001';
            $mcpSignature = self::signature('program06-mcp-secret', $subjectTenant, $primaryActor, $mcpKey, $mcpPayload);
            $mcp = $contractService->authorizeMcp(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-platform',
                capability: 'automation.mcp.invoke',
                version: '2026-08-01',
                idempotencyKey: $mcpKey,
                payload: $mcpPayload,
                signature: $mcpSignature,
            );
            self::assertTrue($mcp->allowed);
            $mcpReplay = $contractService->authorizeMcp(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-platform',
                capability: 'automation.mcp.invoke',
                version: '2026-08-01',
                idempotencyKey: $mcpKey,
                payload: $mcpPayload,
                signature: $mcpSignature,
            );
            self::assertFalse($mcpReplay->allowed);
            self::assertSame('idempotency_replay', $mcpReplay->code);
            $syntheticMcpReplayRejected = true;

            $webhookPayload = '{"event":"program06.webhook"}';
            $invalidBefore = [
                'deliveries' => DB::table('automation_webhook_deliveries')->where('tenant_id', $subjectTenant)->count(),
                'claims' => DB::table('automation_integration_idempotency')->where('tenant_id', $subjectTenant)->where('scope', 'webhook:synthetic-webhook:automation.webhook.receive:2026-08-01')->count(),
            ];
            $invalidWebhook = $contractService->verifyWebhook(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-webhook',
                capability: 'automation.webhook.receive',
                version: '2026-08-01',
                externalId: 'program06-webhook-invalid',
                idempotencyKey: 'program06-webhook-invalid',
                payload: $webhookPayload,
                signature: 'invalid',
            );
            self::assertFalse($invalidWebhook->allowed);
            self::assertSame('invalid_signature', $invalidWebhook->code);
            $invalidAfter = [
                'deliveries' => DB::table('automation_webhook_deliveries')->where('tenant_id', $subjectTenant)->count(),
                'claims' => DB::table('automation_integration_idempotency')->where('tenant_id', $subjectTenant)->where('scope', 'webhook:synthetic-webhook:automation.webhook.receive:2026-08-01')->count(),
            ];
            self::assertSame($invalidBefore, $invalidAfter);
            $webhookExternal = 'program06-webhook-001';
            $webhookKey = 'program06-webhook-key-001';
            $webhookSignature = self::signature('program06-webhook-secret', $subjectTenant, $primaryActor, $webhookKey, $webhookPayload, $webhookExternal);
            $webhook = $contractService->verifyWebhook(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-webhook',
                capability: 'automation.webhook.receive',
                version: '2026-08-01',
                externalId: $webhookExternal,
                idempotencyKey: $webhookKey,
                payload: $webhookPayload,
                signature: $webhookSignature,
            );
            self::assertTrue($webhook->allowed);
            $webhookReplay = $contractService->verifyWebhook(
                tenantId: $subjectTenant,
                actorId: $primaryActor,
                market: 'HN',
                provider: 'synthetic-webhook',
                capability: 'automation.webhook.receive',
                version: '2026-08-01',
                externalId: $webhookExternal,
                idempotencyKey: $webhookKey,
                payload: $webhookPayload,
                signature: $webhookSignature,
            );
            self::assertFalse($webhookReplay->allowed);
            self::assertSame('webhook_replay', $webhookReplay->code);
            $syntheticWebhookReplayRejected = true;
            $invalidSignatureSideEffects = (int) ($invalidAfter['deliveries'] - $invalidBefore['deliveries']) + (int) ($invalidAfter['claims'] - $invalidBefore['claims']);
        } finally {
            config()->set('automation.providers', $previousProviders);
            config()->set('automation.contracts', $previousContracts);
        }
        self::assertSame(0, $invalidSignatureSideEffects);
        self::assertSame($foreignBaseline, Program06CompositeCertificateFixture::tenantState($foreignTenant));

        // 7. Sanitized attribution and forged cross-tenant association.
        $verifiedId = DB::table('verified_commercial_outcomes')->insertGetId([
            'tenant_id' => $subjectTenant,
            'aggregate_type' => 'program06_task',
            'aggregate_id' => 'program06-task-001',
            'outcome' => 'task_completed',
            'projection_key' => 'program06:task:001',
            'amount_minor' => 0,
            'currency' => null,
            'source_occurred_at' => now(),
            'source_version' => 1,
            'metadata' => json_encode(['source' => 'synthetic-local'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        self::assertNotNull($verifiedId);
        $outcomeBefore = DB::table('dovra_agent_outcomes')->where('tenant_id', $subjectTenant)->count();
        $outboxBefore = DB::table('outbox_messages')->count();
        $recorder = new AgentOutcomeRecorder($repository, app(EloquentOutboxRepository::class));
        $outcomeId = $recorder->record($actionResult, 'task_completed', [
            'aggregate_type' => 'program06_task',
            'aggregate_id' => 'program06-task-001',
            'api_token' => 'must-not-survive',
        ]);
        self::assertSame($outcomeBefore + 1, DB::table('dovra_agent_outcomes')->where('tenant_id', $subjectTenant)->count());
        $outcome = DB::table('dovra_agent_outcomes')->where('id', $outcomeId)->first();
        self::assertNotNull($outcome);
        self::assertSame((string) $verifiedId, (string) $outcome->verified_commercial_outcome_id);
        self::assertSame($outcomeId, (string) DB::table('dovra_agent_action_audits')->where('id', $actionResult->audit->id)->value('outcome_id'));
        self::assertStringNotContainsString('must-not-survive', (string) $outcome->attribution_metadata);
        $outboxRows = DB::table('outbox_messages')->orderByDesc('created_at')->limit(DB::table('outbox_messages')->count() - $outboxBefore)->get();
        self::assertSame($outboxBefore + 1, DB::table('outbox_messages')->count());
        self::assertCount(1, $outboxRows);
        self::assertStringNotContainsString('must-not-survive', (string) $outboxRows[0]->payload);
        $foreignAudit = DB::table('dovra_agent_action_audits')->where('tenant_id', $foreignTenant)->first();
        self::assertNotNull($foreignAudit);
        $outcomeIsolationBeforeSubject = Program06CompositeCertificateFixture::tenantState($subjectTenant);
        $outcomeIsolationBeforeForeign = Program06CompositeCertificateFixture::tenantState($foreignTenant);
        $forged = new AgentActionResult(
            executed: true,
            status: 'completed',
            reasonCode: 'completed',
            workflowRunId: (string) $actionResult->workflowRunId,
            replayed: false,
            audit: new AgentActionAudit(
                id: (string) $foreignAudit->id,
                tenantId: $subjectTenant,
                agentVersionId: $primaryActor,
                conversationId: (string) $actionResult->audit->conversationId,
                actionKey: 'certificate.local.read',
                policyCode: 'completed',
                confirmationId: null,
                workflowRunId: (string) $actionResult->workflowRunId,
                outcomeId: null,
                occurredAt: CarbonImmutable::now(),
                metadata: [],
            ),
        );
        $foreignAuditRejected = false;
        try {
            $recorder->record($forged, 'task_completed', ['aggregate_type' => 'program06_task', 'aggregate_id' => 'program06-task-forged']);
        } catch (LogicException $exception) {
            $foreignAuditRejected = $exception->getMessage() === 'Agent action audit is missing or belongs to another tenant.';
        }
        self::assertTrue($foreignAuditRejected);
        self::assertSame($outcomeIsolationBeforeSubject, Program06CompositeCertificateFixture::tenantState($subjectTenant));
        self::assertSame($outcomeIsolationBeforeForeign, Program06CompositeCertificateFixture::tenantState($foreignTenant));
        self::assertSame($foreignBaseline, Program06CompositeCertificateFixture::tenantState($foreignTenant));

        // 8. Final safety and truthful limitation boundaries.
        self::assertSame($baselineSideEffects, $this->sideEffectCounts());
        self::assertSame($previousProviders, config('automation.providers'));
        self::assertSame($previousContracts, config('automation.contracts'));
        $foreignFinalState = Program06CompositeCertificateFixture::tenantState($foreignTenant);
        $foreignTenantMutations = $foreignFinalState === $foreignBaseline ? 0 : 1;
        $foreignActorMutations = $foreignFinalState === $foreignActorPolicyForeignBefore ? 0 : 1;
        self::assertSame(0, $foreignTenantMutations);
        self::assertSame(0, $foreignActorMutations);
        $facts = [
            'schema' => 1,
            'program' => '06',
            'status' => 'LOCALLY_VERIFIED',
            'decision' => 'BOUNDED_LOCAL_ONLY',
            'launch' => 'NO_GO',
            'scope' => 'disposable_sqlite',
            'sequence' => ['guard', 'definition', 'policy', 'action', 'runtime', 'history', 'contracts', 'outcome', 'limitations'],
            'definition' => ['published' => true, 'version' => 'v1', 'immutable' => true],
            'policy' => ['deny_by_default' => true, 'denied_side_effects' => 0, 'read_action_evaluated' => true, 'tool_confirmation_actor_binding' => true],
            'action' => ['executed_once' => true, 'audit_count' => 1, 'run_count' => 1, 'replay_rejected' => true, 'replay_mutations' => 0],
            'runtime' => [
                'wait_persisted' => true,
                'early_resume_read_only' => true,
                'authorized_resume_completed' => true,
                'completed_start_replayed' => true,
                'tenant_isolation_non_vacuous' => $foreignTenantRejected,
                'actor_isolation_non_vacuous' => $sameTenantActorRejected,
            ],
            'history' => [
                'retry_attempts' => (int) $chargeAttempts->count(),
                'retry_keys_distinct' => (string) $chargeAttempts[0]->idempotency_key !== (string) $chargeAttempts[1]->idempotency_key,
                'provider_tool_key_stable' => (string) $chargeAttempts[0]->provider_tool_key === (string) $chargeAttempts[1]->provider_tool_key,
                'compensation_attempts' => (int) $compensationAttempts->count(),
                'compensation_completed' => (string) $compensationAttempts[0]->status === 'completed',
                'terminal_replay_mutations' => 0,
            ],
            'contracts' => [
                'default_fail_closed' => $contractDefaultFailClosed,
                'missing_actor_fail_closed' => $missingActorFailClosed,
                'invalid_signature_side_effects' => $invalidSignatureSideEffects,
                'synthetic_api_replay_rejected' => $syntheticApiReplayRejected,
                'synthetic_mcp_replay_rejected' => $syntheticMcpReplayRejected,
                'synthetic_webhook_replay_rejected' => $syntheticWebhookReplayRejected,
            ],
            'outcome' => [
                'recorded_once' => DB::table('dovra_agent_outcomes')->where('tenant_id', $subjectTenant)->where('id', $outcomeId)->count() === 1,
                'audit_linked' => DB::table('dovra_agent_action_audits')->where('id', $actionResult->audit->id)->where('outcome_id', $outcomeId)->count() === 1,
                'verified_reference_linked' => (string) ($outcome->verified_commercial_outcome_id ?? '') === (string) $verifiedId,
                'sanitized' => ! str_contains((string) $outcome->attribution_metadata, 'must-not-survive') && ! str_contains((string) $outboxRows[0]->payload, 'must-not-survive'),
                'attribution_event_count' => (int) DB::table('outbox_messages')->where('type', BusinessOperationChanged::class)->count(),
                'foreign_audit_rejected' => $foreignAuditRejected,
            ],
            'safety' => [
                'foreign_tenant_sentinel_rows' => (int) $foreignBaseline['row_count'],
                'same_tenant_actor_distinct' => $primaryActor !== $sameTenantActor,
                'foreign_tenant_attempted' => $foreignTenantRejected,
                'foreign_actor_attempted' => $foreignActorAttempted,
                'foreign_tenant_mutations' => $foreignTenantMutations,
                'foreign_actor_mutations' => $foreignActorMutations,
                'provider_calls' => 0,
                'network_calls' => 0,
                'payment_mutations' => 0,
                'billing_mutations' => 0,
                'credit_mutations' => 0,
                'channel_delivery_mutations' => 0,
                'canonical_business_mutations' => 0,
                'config_restored' => true,
            ],
            'limitations' => [
                'workforce_actor_bound_confirmation_proven' => false,
                'canonical_writer_routing_proven' => false,
                'durable_tool_outbox_proven' => false,
                'api_mcp_route_binding_proven' => false,
                'postgresql_rls_runtime_proven' => false,
            ],
            'production_gaps' => [
                'PRODUCTION_GAP_AGENT_CONFIRMATION_ACTOR_BINDING',
                'PRODUCTION_GAP_API_MCP_ROUTE_BINDING',
                'PRODUCTION_GAP_CANONICAL_WRITER_ROUTING',
                'PRODUCTION_GAP_DURABLE_TOOL_OUTBOX',
                'PRODUCTION_GAP_RLS_RUNTIME_CERTIFICATION',
            ],
            'external' => [
                'clean_exact_artifact',
                'independent_security_review',
                'live_channel_and_integration_providers',
                'mysql_portability',
                'postgresql_restricted_role_rls',
                'production_operations',
                'staging_runtime',
            ],
        ];
        self::assertTrue($facts['definition']['published']);
        self::assertSame(2, $facts['history']['retry_attempts']);
        self::assertSame(1, $facts['history']['compensation_attempts']);
        self::assertSame(1, $facts['outcome']['attribution_event_count']);
        self::assertSame(0, $facts['safety']['foreign_tenant_mutations']);
        self::assertSame(0, $facts['safety']['foreign_actor_mutations']);
        $factsPath = (string) getenv('DOVRA_PROGRAM06_COMPOSITE_FACTS');
        self::assertNotSame('', $factsPath);
        Program06CompositeCertificateReport::writeExclusive($factsPath, $facts);
    }

    private static function signature(string $secret, string $tenantId, string $actorId, string $idempotencyKey, string $payload, ?string $externalId = null): string
    {
        $canonical = implode('|', array_filter([$tenantId, $actorId, $externalId, $idempotencyKey, $payload], static fn (?string $value): bool => $value !== null));

        return hash_hmac('sha256', $canonical, $secret);
    }

    /** @return array<string, int> */
    private function sideEffectCounts(): array
    {
        $tables = [
            'payments' => 'payment_mutations',
            'subscription_payments' => 'payment_mutations',
            'global_billing_market_providers' => 'billing_mutations',
            'global_billing_entitlements' => 'billing_mutations',
            'global_billing_credit_accounts' => 'billing_mutations',
            'global_billing_credit_reservations' => 'billing_mutations',
            'global_billing_credit_ledger_entries' => 'billing_mutations',
            'global_billing_credit_alerts' => 'billing_mutations',
            'global_billing_credit_reconciliations' => 'billing_mutations',
            'global_billing_lifecycle_events' => 'billing_mutations',
            'ai_credit_reservations' => 'credit_mutations',
            'ai_credit_ledger_entries' => 'credit_mutations',
            'ai_credit_grants' => 'credit_mutations',
            'ai_credit_usage_alerts' => 'credit_mutations',
            'channel_delivery_attempts' => 'channel_delivery_mutations',
            'channel_delivery_outbox' => 'channel_delivery_mutations',
            'appointments' => 'canonical_business_mutations',
            'leads' => 'canonical_business_mutations',
            'quotes' => 'canonical_business_mutations',
            'orders' => 'canonical_business_mutations',
        ];
        $result = [
            'payment_mutations' => 0,
            'billing_mutations' => 0,
            'credit_mutations' => 0,
            'channel_delivery_mutations' => 0,
            'canonical_business_mutations' => 0,
        ];
        foreach ($tables as $table => $bucket) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Program 06 side-effect table is missing: '.$table);
            }
            $result[$bucket] += (int) DB::table($table)->count();
        }

        return $result;
    }
}
