<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Registry\AiRegistryLifecycle;
use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Registry\Commands\ActivateAgentVersionCommand;
use App\Application\Ai\Registry\Commands\KillSwitchAiVersionCommand;
use App\Application\Ai\Registry\Commands\PromoteAiVersionCommand;
use App\Application\Ai\Registry\Commands\RollbackAgentActivationCommand;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\TestCase;

final class AiRegistryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_is_exact_evaluation_gated_audited_and_transactional(): void
    {
        $fixture = $this->fixture();
        $candidate = $this->eligibleCandidate($fixture);

        $activationId = app(AiRegistryLifecycle::class)->activate(
            new ActivateAgentVersionCommand(
                actorId: (string) $fixture['admin']->id,
                tenantId: (string) $fixture['tenant']->id,
                scopeType: 'tenant',
                scopeKey: (string) $fixture['tenant']->id,
                agentVersionId: $candidate['agent_version_id'],
                releaseDecisionId: $candidate['release_decision_id'],
                rolloutPercentage: 25,
                reason: 'Program 05 internal canary',
                expectedActivationId: $fixture['activation_id'],
                expectedLockVersion: 0,
            ),
        );

        $this->assertDatabaseHas('ai_agent_activations', [
            'id' => $activationId,
            'tenant_id' => (string) $fixture['tenant']->id,
            'agent_version_id' => $candidate['agent_version_id'],
            'release_decision_id' => $candidate['release_decision_id'],
            'rollout_percentage' => 25,
            'previous_activation_id' => $fixture['activation_id'],
            'activated_by' => (string) $fixture['admin']->id,
            'reason' => 'Program 05 internal canary',
            'lock_version' => 0,
        ]);
        self::assertNotNull(DB::table('ai_agent_activations')
            ->where('id', $fixture['activation_id'])
            ->value('effective_until'));
        self::assertSame(1, (int) DB::table('ai_agent_activations')
            ->where('id', $fixture['activation_id'])
            ->value('lock_version'));
        $this->assertDatabaseHas('ai_agent_versions', [
            'id' => $candidate['agent_version_id'],
            'lifecycle_status' => 'active',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'ai_registry.agent_activated',
            'causer_id' => (string) $fixture['admin']->id,
        ]);
        self::assertSame(1, DB::table('outbox_messages')
            ->where('type', 'App\\Domain\\Ai\\Events\\AiRegistryChanged')
            ->where('aggregate_id', $activationId)
            ->count());
    }

    public function test_activation_rejects_failed_revoked_expired_wrong_checksum_and_wrong_lineage_decisions(): void
    {
        foreach (['failed', 'revoked', 'expired', 'checksum', 'lineage'] as $case) {
            $fixture = $this->fixture('decision-'.$case);
            $candidate = $this->eligibleCandidate($fixture);

            match ($case) {
                'failed' => DB::table('ai_release_decisions')
                    ->where('id', $candidate['release_decision_id'])
                    ->update(['decision' => 'failed']),
                'revoked' => DB::table('ai_release_decisions')
                    ->where('id', $candidate['release_decision_id'])
                    ->update(['revoked_at' => now(), 'revoked_by' => (string) $fixture['admin']->id]),
                'expired' => DB::table('ai_release_decisions')
                    ->where('id', $candidate['release_decision_id'])
                    ->update(['valid_until' => now()->subSecond()]),
                'checksum' => DB::table('ai_release_decisions')
                    ->where('id', $candidate['release_decision_id'])
                    ->update(['candidate_checksum' => str_repeat('0', 64)]),
                'lineage' => DB::table('ai_evaluation_runs')
                    ->where('id', $candidate['evaluation_run_id'])
                    ->update(['agent_version_id' => $fixture['selection']->agentVersionId]),
            };

            try {
                app(AiRegistryLifecycle::class)->activate(
                    new ActivateAgentVersionCommand(
                        actorId: (string) $fixture['admin']->id,
                        tenantId: (string) $fixture['tenant']->id,
                        scopeType: 'tenant',
                        scopeKey: (string) $fixture['tenant']->id,
                        agentVersionId: $candidate['agent_version_id'],
                        releaseDecisionId: $candidate['release_decision_id'],
                        rolloutPercentage: 100,
                        reason: 'Reject invalid exact release decision',
                        expectedActivationId: $fixture['activation_id'],
                        expectedLockVersion: 0,
                    ),
                );
                self::fail("Expected [{$case}] decision rejection.");
            } catch (AiExecutionDeniedException $exception) {
                self::assertSame('evaluation_gate', $exception->reason);
            }

            self::assertSame(1, DB::table('ai_agent_activations')
                ->where('tenant_id', $fixture['tenant']->id)
                ->whereNull('effective_until')
                ->count());
        }
    }

    public function test_activation_rejects_unauthorized_actor_invalid_scope_bounds_and_stale_optimistic_version(): void
    {
        $fixture = $this->fixture();
        $candidate = $this->eligibleCandidate($fixture);
        $ordinary = User::factory()->create();

        $base = [
            'actorId' => (string) $fixture['admin']->id,
            'tenantId' => (string) $fixture['tenant']->id,
            'scopeType' => 'tenant',
            'scopeKey' => (string) $fixture['tenant']->id,
            'agentVersionId' => $candidate['agent_version_id'],
            'releaseDecisionId' => $candidate['release_decision_id'],
            'rolloutPercentage' => 100,
            'reason' => 'Validate governed registry mutation',
            'expectedActivationId' => $fixture['activation_id'],
            'expectedLockVersion' => 0,
        ];

        foreach ([
            ['actorId' => (string) $ordinary->id],
            ['scopeType' => 'tenant', 'scopeKey' => (string) Str::uuid()],
            ['rolloutPercentage' => -1],
            ['rolloutPercentage' => 101],
            ['expectedLockVersion' => 9],
        ] as $override) {
            try {
                app(AiRegistryLifecycle::class)->activate(
                    new ActivateAgentVersionCommand(...array_merge($base, $override)),
                );
                self::fail('Expected governed activation validation failure.');
            } catch (
                ValidationException
                |AuthorizationException
                |AiExecutionDeniedException
            ) {
                self::assertTrue(true);
            }
        }
    }

    public function test_ambiguous_open_scope_fails_without_mutation(): void
    {
        $fixture = $this->fixture();
        $candidate = $this->eligibleCandidate($fixture);
        $duplicateId = (string) Str::uuid();
        DB::table('ai_agent_activations')->insert([
            'id' => $duplicateId,
            'tenant_id' => (string) $fixture['tenant']->id,
            'scope_type' => 'tenant',
            'scope_key' => (string) $fixture['tenant']->id,
            'agent_version_id' => $fixture['selection']->agentVersionId,
            'rollout_percentage' => 100,
            'cohort_salt' => hash('sha256', $duplicateId),
            'release_decision_id' => $fixture['selection']->releaseDecisionId,
            'previous_activation_id' => null,
            'effective_from' => now()->subSecond(),
            'effective_until' => null,
            'activated_by' => 'fixture',
            'reason' => 'Ambiguity fixture',
            'lock_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(AiRegistryLifecycle::class)->activate(
                new ActivateAgentVersionCommand(
                    actorId: (string) $fixture['admin']->id,
                    tenantId: (string) $fixture['tenant']->id,
                    scopeType: 'tenant',
                    scopeKey: (string) $fixture['tenant']->id,
                    agentVersionId: $candidate['agent_version_id'],
                    releaseDecisionId: $candidate['release_decision_id'],
                    rolloutPercentage: 100,
                    reason: 'Reject ambiguous activation scope',
                    expectedActivationId: $fixture['activation_id'],
                    expectedLockVersion: 0,
                ),
            );
            self::fail('Expected ambiguous activation scope denial.');
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('registry_conflict', $exception->reason);
        }

        self::assertSame(2, DB::table('ai_agent_activations')
            ->where('tenant_id', $fixture['tenant']->id)
            ->whereNull('effective_until')
            ->count());
    }

    public function test_rollback_creates_new_history_for_a_still_eligible_prior_version(): void
    {
        $fixture = $this->fixture();
        $candidate = $this->eligibleCandidate($fixture);
        $lifecycle = app(AiRegistryLifecycle::class);
        $canaryId = $lifecycle->activate(new ActivateAgentVersionCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            scopeType: 'tenant',
            scopeKey: (string) $fixture['tenant']->id,
            agentVersionId: $candidate['agent_version_id'],
            releaseDecisionId: $candidate['release_decision_id'],
            rolloutPercentage: 100,
            reason: 'Activate replacement before rollback',
            expectedActivationId: $fixture['activation_id'],
            expectedLockVersion: 0,
        ));

        $rollbackId = $lifecycle->rollback(new RollbackAgentActivationCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            activationId: $canaryId,
            expectedLockVersion: 0,
            reason: 'Rollback after governed canary regression',
        ));

        $this->assertDatabaseHas('ai_agent_activations', [
            'id' => $rollbackId,
            'agent_version_id' => $fixture['selection']->agentVersionId,
            'release_decision_id' => $fixture['selection']->releaseDecisionId,
            'previous_activation_id' => $canaryId,
            'rollout_percentage' => 100,
        ]);
        self::assertNotNull(DB::table('ai_agent_activations')
            ->where('id', $canaryId)
            ->value('effective_until'));
        self::assertSame(1, DB::table('outbox_messages')
            ->where('aggregate_id', $rollbackId)
            ->count());
    }

    public function test_kill_switch_blocks_exact_version_and_runtime_fails_closed(): void
    {
        $fixture = $this->fixture();

        app(AiRegistryLifecycle::class)->killSwitch(new KillSwitchAiVersionCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            versionType: 'agent',
            versionId: $fixture['selection']->agentVersionId,
            reason: 'Emergency stop after safety regression',
        ));

        $this->assertDatabaseHas('ai_agent_versions', [
            'id' => $fixture['selection']->agentVersionId,
            'lifecycle_status' => 'blocked',
            'kill_switch_enabled' => true,
            'blocked_by' => (string) $fixture['admin']->id,
        ]);
        self::assertNotNull(DB::table('ai_agent_activations')
            ->where('id', $fixture['activation_id'])
            ->value('effective_until'));

        $this->expectException(AiExecutionDeniedException::class);
        app(AiRegistryResolver::class)->resolve(new AiRegistryQuery(
            tenantId: (string) $fixture['tenant']->id,
            audience: 'private',
            capability: 'private_agent',
            channel: 'web',
            marketCountryCode: 'HN',
            actorId: (string) $fixture['admin']->id,
            cohortSubject: (string) $fixture['admin']->id,
        ));
    }

    public function test_promotion_only_advances_one_allowed_transition_and_records_candidacy(): void
    {
        $fixture = $this->fixture();
        $definitionId = (string) DB::table('ai_agent_versions')
            ->where('id', $fixture['selection']->agentVersionId)
            ->value('agent_definition_id');
        $draftId = (string) Str::uuid();
        DB::table('ai_agent_versions')->insert([
            'id' => $draftId,
            'tenant_id' => (string) $fixture['tenant']->id,
            'agent_definition_id' => $definitionId,
            'model_version_id' => $fixture['selection']->modelVersionId,
            'version' => 2,
            'checksum' => hash('sha256', $draftId),
            'configuration' => DB::table('ai_agent_versions')
                ->where('id', $fixture['selection']->agentVersionId)
                ->value('configuration'),
            'released_at' => null,
            'lifecycle_status' => 'draft',
            'kill_switch_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AiRegistryLifecycle::class)->promote(new PromoteAiVersionCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            versionType: 'agent',
            versionId: $draftId,
            targetStatus: 'candidate',
            releaseDecisionId: null,
            reason: 'Freeze candidate for deterministic evaluation',
        ));

        $this->assertDatabaseHas('ai_agent_versions', [
            'id' => $draftId,
            'lifecycle_status' => 'candidate',
        ]);
        self::assertNotNull(DB::table('ai_agent_versions')
            ->where('id', $draftId)
            ->value('released_at'));

        $this->expectException(ValidationException::class);
        app(AiRegistryLifecycle::class)->promote(new PromoteAiVersionCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            versionType: 'agent',
            versionId: $draftId,
            targetStatus: 'active',
            releaseDecisionId: null,
            reason: 'Attempt to skip mandatory evaluation state',
        ));
    }

    /** @return array{admin:User,tenant:Tenant,selection:object,activation_id:string} */
    private function fixture(string $slug = 'lifecycle'): array
    {
        config()->set('activitylog.enabled', true);
        $admin = User::factory()->superAdmin()->create([
            'email' => fake()->unique()->safeEmail(),
        ]);
        config()->set('auth.super_admins', [$admin->email]);
        $tenant = Tenant::factory()->create(['slug' => $slug.'-'.Str::lower(Str::random(6))]);
        config()->set('ai.public.billing_tenant_id', (string) $tenant->id);
        $selection = AiEligibleRegistryFixture::create(
            tenantId: (string) $tenant->id,
            capability: 'private_agent',
            audience: 'private',
            outputSchema: 'private_agent.answer.v1',
        );
        $activationId = (string) DB::table('ai_agent_activations')
            ->where('tenant_id', $tenant->id)
            ->where('agent_version_id', $selection->agentVersionId)
            ->value('id');

        return compact('admin', 'tenant', 'selection', 'activationId')
            + ['activation_id' => $activationId];
    }

    /**
     * @param  array{admin:User,tenant:Tenant,selection:object,activation_id:string}  $fixture
     * @return array{agent_version_id:string,release_decision_id:string,evaluation_run_id:string}
     */
    private function eligibleCandidate(array $fixture): array
    {
        $current = DB::table('ai_agent_versions')
            ->where('id', $fixture['selection']->agentVersionId)
            ->first();
        self::assertNotNull($current);
        $agentVersionId = (string) Str::uuid();
        $checksum = hash('sha256', $agentVersionId);
        DB::table('ai_agent_versions')->insert([
            'id' => $agentVersionId,
            'tenant_id' => (string) $fixture['tenant']->id,
            'agent_definition_id' => $current->agent_definition_id,
            'model_version_id' => $fixture['selection']->modelVersionId,
            'version' => ((int) $current->version) + 1,
            'checksum' => $checksum,
            'configuration' => $current->configuration,
            'released_at' => now()->subSecond(),
            'lifecycle_status' => 'eligible',
            'kill_switch_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evaluationVersionId = (string) DB::table('ai_evaluation_runs')
            ->where('agent_version_id', $fixture['selection']->agentVersionId)
            ->value('evaluation_version_id');
        $evaluationRunId = (string) Str::uuid();
        DB::table('ai_evaluation_runs')->insert([
            'id' => $evaluationRunId,
            'tenant_id' => (string) $fixture['tenant']->id,
            'evaluation_version_id' => $evaluationVersionId,
            'agent_version_id' => $agentVersionId,
            'prompt_version_id' => $fixture['selection']->promptVersionId,
            'provider_version_id' => $fixture['selection']->providerVersionId,
            'model_version_id' => $fixture['selection']->modelVersionId,
            'status' => 'completed',
            'triggered_by' => (string) $fixture['admin']->id,
            'started_at' => now()->subSeconds(3),
            'completed_at' => now()->subSeconds(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (DB::table('ai_evaluation_cases')
            ->where('evaluation_version_id', $evaluationVersionId)
            ->pluck('id') as $caseId) {
            DB::table('ai_evaluation_results')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => (string) $fixture['tenant']->id,
                'evaluation_run_id' => $evaluationRunId,
                'evaluation_case_id' => $caseId,
                'status' => 'passed',
                'score_minor' => 100,
                'completed_at' => now()->subSecond(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $releaseDecisionId = (string) Str::uuid();
        $policyId = (string) DB::table('ai_release_decisions')
            ->where('id', $fixture['selection']->releaseDecisionId)
            ->value('release_policy_id');
        DB::table('ai_release_decisions')->insert([
            'id' => $releaseDecisionId,
            'tenant_id' => (string) $fixture['tenant']->id,
            'release_policy_id' => $policyId,
            'evaluation_run_id' => $evaluationRunId,
            'agent_version_id' => $agentVersionId,
            'decision' => 'passed',
            'decided_by' => (string) $fixture['admin']->id,
            'evidence_hash' => hash('sha256', $releaseDecisionId),
            'rationale' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'decided_at' => now(),
            'candidate_checksum' => $checksum,
            'valid_from' => now()->subSecond(),
            'valid_until' => now()->addDay(),
            'revoked_at' => null,
            'revoked_by' => null,
            'revocation_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('agentVersionId', 'releaseDecisionId', 'evaluationRunId')
            + [
                'agent_version_id' => $agentVersionId,
                'release_decision_id' => $releaseDecisionId,
                'evaluation_run_id' => $evaluationRunId,
            ];
    }
}
