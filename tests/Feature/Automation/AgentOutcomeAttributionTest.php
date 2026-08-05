<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Application\Automation\AgentAction\AgentActionAudit;
use App\Application\Automation\AgentAction\AgentActionResult;
use App\Application\Automation\AgentAction\AgentOutcomeRecorder;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use App\Infrastructure\Shared\Persistence\EloquentOutboxRepository;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AgentOutcomeAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_outcomes_are_sanitized_tenant_scoped_and_link_a_stronger_verified_commercial_outcome(): void
    {
        [$tenant] = $this->tenant();
        $auditId = (string) Str::uuid();
        DB::table('dovra_agent_action_audits')->insert([
            'id' => $auditId,
            'tenant_id' => $tenant->getKey(),
            'agent_version_id' => (string) Str::uuid(),
            'conversation_id' => (string) Str::uuid(),
            'action_key' => 'agent.order.follow-up',
            'idempotency_key' => 'outcome-attribution-001',
            'input_hash' => hash('sha256', 'safe-input'),
            'risk' => 'read',
            'policy_code' => 'policy_authorized',
            'status' => 'completed',
            'metadata' => json_encode([
                'customer_email' => 'not-stored@example.test',
                'workflow_status' => 'completed',
                'workflow_reason_code' => 'completed',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => $tenant->getKey(),
            'aggregate_type' => 'order',
            'aggregate_id' => '42',
            'outcome' => 'order_paid',
            'projection_key' => $tenant->getKey().':order:42:order_paid',
            'amount_minor' => 12500,
            'currency' => 'HNL',
            'source_occurred_at' => now(),
            'source_version' => 1,
            'metadata' => json_encode(['authority' => 'server_aggregate'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $result = new AgentActionResult(
            executed: true,
            status: 'completed',
            reasonCode: 'completed',
            workflowRunId: (string) Str::uuid(),
            replayed: false,
            audit: new AgentActionAudit(
                id: $auditId,
                tenantId: (string) $tenant->getKey(),
                agentVersionId: (string) DB::table('dovra_agent_action_audits')->where('id', $auditId)->value('agent_version_id'),
                conversationId: (string) DB::table('dovra_agent_action_audits')->where('id', $auditId)->value('conversation_id'),
                actionKey: 'agent.order.follow-up',
                policyCode: 'policy_authorized',
                confirmationId: null,
                workflowRunId: null,
                outcomeId: null,
                occurredAt: CarbonImmutable::now(),
                metadata: [
                    'api_key' => 'must-not-leak',
                    'aggregate_id' => '42',
                    'workflow_status' => 'completed',
                    'workflow_reason_code' => 'completed',
                ],
            ),
        );

        $recorder = new AgentOutcomeRecorder(
            new DatabaseWorkflowRepository(DB::connection()),
            app(EloquentOutboxRepository::class),
        );
        $reservationOutcomeId = $recorder->record($result, 'reservation_created', [
            'aggregate_type' => 'reservation',
            'aggregate_id' => 'reservation-7',
        ]);
        $quoteOutcomeId = $recorder->record($result, 'quote_created', [
            'aggregate_type' => 'quote',
            'aggregate_id' => 'quote-9',
        ]);
        $outcomeId = $recorder->record($result, 'order_created', [
            'aggregate_type' => 'order',
            'aggregate_id' => '42',
            'amount_minor' => 12500,
            'currency' => 'hnl',
            'authorization' => 'must-not-leak',
        ]);

        $outcome = DB::table('dovra_agent_outcomes')->where('id', $outcomeId)->first();
        self::assertNotNull($outcome);
        self::assertSame((string) $tenant->getKey(), (string) $outcome->tenant_id);
        self::assertSame('HNL', (string) $outcome->currency);
        self::assertNotNull($outcome->verified_commercial_outcome_id);
        self::assertStringNotContainsString('must-not-leak', (string) $outcome->attribution_metadata);
        $this->assertDatabaseHas('dovra_agent_action_audits', ['id' => $auditId, 'outcome_id' => $outcomeId]);
        $audit = DB::table('dovra_agent_action_audits')->where('id', $auditId)->first();
        self::assertNotNull($audit);
        self::assertSame('policy_authorized', (string) $audit->policy_code);
        self::assertSame('completed', (string) $audit->status);
        $auditMetadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('completed', $auditMetadata['workflow_status'] ?? null);
        self::assertSame('completed', $auditMetadata['workflow_reason_code'] ?? null);
        self::assertSame(3, DB::table('dovra_agent_outcomes')->whereIn('id', [
            $reservationOutcomeId,
            $quoteOutcomeId,
            $outcomeId,
        ])->count());
    }

    /** @return array{Tenant, User} */
    private function tenant(): array
    {
        /** @var array{Tenant, User} $result */
        return Model::withoutEvents(function (): array {
            $actor = User::factory()->create();
            $tenant = Tenant::factory()->forUser($actor)->pro()->create();

            return [$tenant, $actor];
        });
    }
}
