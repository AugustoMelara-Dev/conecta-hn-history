<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Application\Automation\ToolPolicyEngine;
use App\Application\Automation\WorkflowDefinitionService;
use App\Application\Automation\WorkflowRuntime;
use App\Domain\Automation\WorkflowStartRequest;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WorkflowConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_is_idempotent_and_returns_the_existing_run_for_the_same_tenant_version_and_key(): void
    {
        [$tenant, $actor] = $this->tenantActor();
        $repository = new DatabaseWorkflowRepository(DB::connection());
        $definitions = new WorkflowDefinitionService($repository);

        $draft = $definitions->createDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'ops.sync',
            definition: ['steps' => [['id' => 'noop', 'type' => 'tool', 'action' => 'noop']]],
            actorId: (string) $actor->getKey(),
        );
        $definitions->publishDraft(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'ops.sync',
            draftVersionId: $draft['version_id'],
            publishedVersion: 'v1',
            actorId: (string) $actor->getKey(),
        );

        $runtime = new WorkflowRuntime(
            definitions: $definitions,
            runs: $repository,
            policyEngine: new ToolPolicyEngine($repository),
            toolHandlers: ['noop' => static fn (): array => ['output' => ['ok' => true], 'duration_seconds' => 1]],
        );

        $request = new WorkflowStartRequest(
            tenantId: (string) $tenant->getKey(),
            workflowKey: 'ops.sync',
            workflowVersion: 'v1',
            actorId: (string) $actor->getKey(),
            actorRoles: ['owner'],
            plan: 'pro',
            channel: 'api',
            idempotencyKey: 'ops-sync-001',
            trigger: 'schedule',
            payload: ['batch' => '2026-08-02'],
            confirmations: [],
            requestedAt: CarbonImmutable::parse('2026-08-02T18:00:00+00:00'),
        );

        $first = $runtime->start($request);
        $second = $runtime->start($request);

        self::assertSame($first->runId, $second->runId);
        self::assertFalse($first->replayed);
        self::assertTrue($second->replayed);
        self::assertSame(
            1,
            DB::table('automation_workflow_runs')
                ->where('tenant_id', $tenant->getKey())
                ->where('workflow_key', 'ops.sync')
                ->where('idempotency_key', 'ops-sync-001')
                ->count(),
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
