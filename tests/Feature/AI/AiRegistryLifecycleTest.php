<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\Registry\AiRegistryLifecycle;
use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Registry\Commands\ActivateAgentVersionCommand;
use App\Application\Ai\Registry\Commands\CreateAiDraftCommand;
use App\Application\Ai\Registry\Commands\KillSwitchAiVersionCommand;
use App\Application\Ai\Registry\Commands\PromoteAiVersionCommand;
use App\Application\Ai\Registry\Commands\RevokeAiReleaseDecisionCommand;
use App\Application\Ai\Registry\Commands\RollbackAgentActivationCommand;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\Support\AI\AiEligibleRegistryFixture;
use Tests\TestCase;

final class AiRegistryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_decision_revocation_is_governed_idempotent_audited_and_closes_assignments(): void
    {
        $fixture = $this->fixture('governed-revocation');
        $decisionId = $fixture['selection']->releaseDecisionId;
        $reason = 'Revoke the evaluated release after a governance review.';
        $lifecycle = app(AiRegistryLifecycle::class);

        $command = new RevokeAiReleaseDecisionCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            decisionId: $decisionId,
            reason: $reason,
        );
        $lifecycle->revokeReleaseDecision($command);

        $decision = DB::table('ai_release_decisions')->where('id', $decisionId)->first();
        self::assertNotNull($decision);
        self::assertNotNull($decision->revoked_at);
        self::assertSame((string) $fixture['admin']->id, $decision->revoked_by);
        self::assertSame($reason, $decision->revocation_reason);
        self::assertNotNull(DB::table('ai_agent_activations')
            ->where('id', $fixture['activation_id'])
            ->value('effective_until'));
        self::assertSame(1, (int) DB::table('ai_agent_activations')
            ->where('id', $fixture['activation_id'])
            ->value('lock_version'));
        $activityCount = DB::table('activity_log')->count();
        $outboxCount = DB::table('outbox_messages')->count();

        $lifecycle->revokeReleaseDecision($command);
        self::assertSame($activityCount, DB::table('activity_log')->count());
        self::assertSame($outboxCount, DB::table('outbox_messages')->count());

        try {
            $lifecycle->revokeReleaseDecision(new RevokeAiReleaseDecisionCommand(
                actorId: (string) $fixture['admin']->id,
                tenantId: (string) $fixture['tenant']->id,
                decisionId: $decisionId,
                reason: 'Attempt to replace the durable revocation reason.',
            ));
            self::fail('Expected conflicting repeated revocation rejection.');
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('registry_conflict', $exception->reason);
        }

        try {
            $lifecycle->activate(new ActivateAgentVersionCommand(
                actorId: (string) $fixture['admin']->id,
                tenantId: (string) $fixture['tenant']->id,
                scopeType: 'tenant',
                scopeKey: (string) $fixture['tenant']->id,
                agentVersionId: $fixture['selection']->agentVersionId,
                releaseDecisionId: $decisionId,
                rolloutPercentage: 100,
                reason: 'Attempt activation after release revocation.',
                expectedActivationId: null,
                expectedLockVersion: null,
            ));
            self::fail('Expected revoked release activation denial.');
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('evaluation_gate', $exception->reason);
        }
    }

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
        $this->assertDatabaseHas('ai_agent_versions', [
            'id' => $fixture['selection']->agentVersionId,
            'lifecycle_status' => 'active',
        ]);

        $salt = (string) DB::table('ai_agent_activations')
            ->where('id', $activationId)
            ->value('cohort_salt');
        [$canarySubject, $controlSubject] = $this->cohortSubjects($salt, 25);
        self::assertSame(
            $candidate['agent_version_id'],
            app(AiRegistryResolver::class)->resolve(
                $this->registryQuery($fixture, $canarySubject),
            )->agentVersionId,
        );
        self::assertSame(
            $fixture['selection']->agentVersionId,
            app(AiRegistryResolver::class)->resolve(
                $this->registryQuery($fixture, $controlSubject),
            )->agentVersionId,
        );

        DB::table('ai_agent_activations')->where('id', $activationId)->update([
            'rollout_percentage' => 0,
        ]);
        self::assertSame(
            $fixture['selection']->agentVersionId,
            app(AiRegistryResolver::class)->resolve(
                $this->registryQuery($fixture, $canarySubject),
            )->agentVersionId,
        );
        DB::table('ai_agent_activations')->where('id', $activationId)->update([
            'rollout_percentage' => 100,
        ]);
        self::assertSame(
            $candidate['agent_version_id'],
            app(AiRegistryResolver::class)->resolve(
                $this->registryQuery($fixture, $controlSubject),
            )->agentVersionId,
        );
        $this->assertDatabaseHas('activity_log', [
            'description' => 'ai_registry.agent_activated',
            'causer_id' => (string) $fixture['admin']->id,
        ]);
        self::assertSame(1, DB::table('outbox_messages')
            ->where('type', 'App\\Domain\\Ai\\Events\\AiRegistryChanged')
            ->where('aggregate_id', $activationId)
            ->count());
    }

    public function test_canary_progression_keeps_the_root_control_until_full_rollout(): void
    {
        $fixture = $this->fixture('canary-progression');
        $candidate = $this->eligibleCandidate($fixture);
        $lifecycle = app(AiRegistryLifecycle::class);
        $rootActivationId = $fixture['activation_id'];
        $rootAgentVersionId = $fixture['selection']->agentVersionId;
        $currentActivationId = $rootActivationId;

        $activate = function (int $rolloutPercentage) use (
            $candidate,
            $fixture,
            $lifecycle,
            &$currentActivationId,
        ): string {
            $currentActivationId = $lifecycle->activate(new ActivateAgentVersionCommand(
                actorId: (string) $fixture['admin']->id,
                tenantId: (string) $fixture['tenant']->id,
                scopeType: 'tenant',
                scopeKey: (string) $fixture['tenant']->id,
                agentVersionId: $candidate['agent_version_id'],
                releaseDecisionId: $candidate['release_decision_id'],
                rolloutPercentage: $rolloutPercentage,
                reason: "Govern candidate at {$rolloutPercentage} percent",
                expectedActivationId: $currentActivationId,
                expectedLockVersion: 0,
            ));

            return $currentActivationId;
        };

        $zeroId = $activate(0);
        $salt = (string) DB::table('ai_agent_activations')
            ->where('id', $zeroId)
            ->value('cohort_salt');
        [$canarySubject, $controlSubject] = $this->cohortSubjects($salt, 25);
        self::assertSame(
            $rootAgentVersionId,
            app(AiRegistryResolver::class)->resolve(
                $this->registryQuery($fixture, $canarySubject),
            )->agentVersionId,
        );
        self::assertSame($rootActivationId, DB::table('ai_agent_activations')
            ->where('id', $zeroId)
            ->value('previous_activation_id'));

        $twentyFiveId = $activate(25);
        self::assertSame($rootActivationId, DB::table('ai_agent_activations')
            ->where('id', $twentyFiveId)
            ->value('previous_activation_id'));
        self::assertSame(
            $candidate['agent_version_id'],
            app(AiRegistryResolver::class)->resolve(
                $this->registryQuery($fixture, $canarySubject),
            )->agentVersionId,
        );
        self::assertSame(
            $rootAgentVersionId,
            app(AiRegistryResolver::class)->resolve(
                $this->registryQuery($fixture, $controlSubject),
            )->agentVersionId,
        );
        $this->assertDatabaseHas('ai_agent_versions', [
            'id' => $rootAgentVersionId,
            'lifecycle_status' => 'active',
        ]);

        $hundredId = $activate(100);
        self::assertSame($rootActivationId, DB::table('ai_agent_activations')
            ->where('id', $hundredId)
            ->value('previous_activation_id'));
        foreach ([$canarySubject, $controlSubject] as $subject) {
            self::assertSame(
                $candidate['agent_version_id'],
                app(AiRegistryResolver::class)->resolve(
                    $this->registryQuery($fixture, $subject),
                )->agentVersionId,
            );
        }
        $this->assertDatabaseHas('ai_agent_versions', [
            'id' => $rootAgentVersionId,
            'lifecycle_status' => 'eligible',
        ]);

        $rollbackId = $lifecycle->rollback(new RollbackAgentActivationCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            activationId: $hundredId,
            expectedLockVersion: 0,
            reason: 'Rollback governed full rollout to root control',
        ));
        $this->assertDatabaseHas('ai_agent_activations', [
            'id' => $rollbackId,
            'agent_version_id' => $rootAgentVersionId,
            'previous_activation_id' => $hundredId,
            'rollout_percentage' => 100,
        ]);
    }

    public function test_partial_activation_rejects_a_revoked_or_killed_previous_release(): void
    {
        foreach (['revoked', 'killed'] as $case) {
            $fixture = $this->fixture('previous-'.$case);
            $candidate = $this->eligibleCandidate($fixture);
            $zeroActivationId = app(AiRegistryLifecycle::class)->activate(
                new ActivateAgentVersionCommand(
                    actorId: (string) $fixture['admin']->id,
                    tenantId: (string) $fixture['tenant']->id,
                    scopeType: 'tenant',
                    scopeKey: (string) $fixture['tenant']->id,
                    agentVersionId: $candidate['agent_version_id'],
                    releaseDecisionId: $candidate['release_decision_id'],
                    rolloutPercentage: 0,
                    reason: 'Establish the governed zero-percent canary',
                    expectedActivationId: $fixture['activation_id'],
                    expectedLockVersion: 0,
                ),
            );

            if ($case === 'revoked') {
                DB::table('ai_release_decisions')
                    ->where('id', $fixture['selection']->releaseDecisionId)
                    ->update([
                        'revoked_at' => now(),
                        'revoked_by' => (string) $fixture['admin']->id,
                    ]);
            } else {
                DB::table('ai_agent_versions')
                    ->where('id', $fixture['selection']->agentVersionId)
                    ->update([
                        'lifecycle_status' => 'blocked',
                        'kill_switch_enabled' => true,
                    ]);
            }

            try {
                app(AiRegistryLifecycle::class)->activate(
                    new ActivateAgentVersionCommand(
                        actorId: (string) $fixture['admin']->id,
                        tenantId: (string) $fixture['tenant']->id,
                        scopeType: 'tenant',
                        scopeKey: (string) $fixture['tenant']->id,
                        agentVersionId: $candidate['agent_version_id'],
                        releaseDecisionId: $candidate['release_decision_id'],
                        rolloutPercentage: 25,
                        reason: 'Reject an unsafe previous canary release',
                        expectedActivationId: $zeroActivationId,
                        expectedLockVersion: 0,
                    ),
                );
                self::fail("Expected the {$case} previous release to be rejected.");
            } catch (AiExecutionDeniedException $exception) {
                self::assertSame('evaluation_gate', $exception->reason);
            }

            self::assertSame(1, DB::table('ai_agent_activations')
                ->where('tenant_id', $fixture['tenant']->id)
                ->whereNull('effective_until')
                ->count());
            $this->assertDatabaseHas('ai_agent_activations', [
                'id' => $zeroActivationId,
                'rollout_percentage' => 0,
                'effective_until' => null,
            ]);
        }
    }

    public function test_activation_rejects_failed_revoked_expired_wrong_checksum_and_wrong_lineage_decisions(): void
    {
        foreach ([
            'failed',
            'revoked',
            'expired',
            'checksum',
            'lineage',
            'future_evaluation',
            'result_before_run',
            'result_after_run',
        ] as $case) {
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
                'future_evaluation' => DB::table('ai_evaluation_versions')
                    ->where('id', DB::table('ai_evaluation_runs')
                        ->where('id', $candidate['evaluation_run_id'])
                        ->value('evaluation_version_id'))
                    ->update(['released_at' => now()->addMinute()]),
                'result_before_run' => DB::table('ai_evaluation_results')
                    ->where('evaluation_run_id', $candidate['evaluation_run_id'])
                    ->update(['completed_at' => now()->subSeconds(4)]),
                'result_after_run' => DB::table('ai_evaluation_results')
                    ->where('evaluation_run_id', $candidate['evaluation_run_id'])
                    ->update(['completed_at' => now()->subSecond()]),
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

    public function test_activation_rejects_every_static_compatibility_mismatch(): void
    {
        foreach ([
            'visibility',
            'channel',
            'market',
            'capability',
            'policy_extra_capability',
            'output_schema',
            'max_output_tokens',
            'wildcard_scope',
            'deployment',
        ] as $case) {
            $fixture = $this->fixture('static-'.$case);
            $candidate = $this->eligibleCandidate($fixture);
            $scopeType = 'tenant';
            $scopeKey = (string) $fixture['tenant']->id;

            if ($case === 'visibility') {
                $definitionId = DB::table('ai_agent_versions')
                    ->where('id', $candidate['agent_version_id'])
                    ->value('agent_definition_id');
                DB::table('ai_agent_definitions')
                    ->where('id', $definitionId)
                    ->update(['visibility' => 'platform_public']);
            } elseif ($case === 'channel') {
                $configuration = json_decode(
                    (string) DB::table('ai_agent_versions')
                        ->where('id', $candidate['agent_version_id'])
                        ->value('configuration'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $configuration['channels'] = ['mobile'];
                DB::table('ai_agent_versions')
                    ->where('id', $candidate['agent_version_id'])
                    ->update(['configuration' => json_encode($configuration, JSON_THROW_ON_ERROR)]);
                $scopeType = 'tenant_channel';
                $scopeKey .= '|web';
            } elseif ($case === 'market') {
                $configuration = json_decode(
                    (string) DB::table('ai_model_versions')
                        ->where('id', $fixture['selection']->modelVersionId)
                        ->value('configuration'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $configuration['allowed_market_country_codes'] = ['HN'];
                DB::table('ai_model_versions')
                    ->where('id', $fixture['selection']->modelVersionId)
                    ->update(['configuration' => json_encode(
                        $configuration,
                        JSON_THROW_ON_ERROR,
                    )]);
                $scopeType = 'market';
                $scopeKey = 'CA';
            } elseif ($case === 'capability') {
                DB::table('ai_model_versions')
                    ->where('id', $fixture['selection']->modelVersionId)
                    ->update(['configuration' => json_encode([
                        'capabilities' => ['vision'],
                        'allowed_market_country_codes' => ['HN'],
                        'residency_country_codes' => ['HN'],
                    ], JSON_THROW_ON_ERROR)]);
            } elseif ($case === 'policy_extra_capability') {
                $decision = DB::table('ai_release_decisions')
                    ->where('id', $candidate['release_decision_id'])
                    ->first();
                DB::table('ai_release_policies')
                    ->where('id', $decision->release_policy_id)
                    ->update(['rules' => json_encode([
                        'required_capabilities' => ['structured_output', 'vision'],
                        'allowed_market_country_codes' => ['HN'],
                        'residency_country_codes' => ['HN'],
                    ], JSON_THROW_ON_ERROR)]);
            } elseif (in_array($case, ['output_schema', 'max_output_tokens'], true)) {
                $configuration = json_decode(
                    (string) DB::table('ai_agent_versions')
                        ->where('id', $candidate['agent_version_id'])
                        ->value('configuration'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $configuration[$case] = $case === 'output_schema' ? '' : 0;
                DB::table('ai_agent_versions')
                    ->where('id', $candidate['agent_version_id'])
                    ->update(['configuration' => json_encode($configuration, JSON_THROW_ON_ERROR)]);
            } elseif ($case === 'wildcard_scope') {
                $scopeType = 'platform';
                $scopeKey = '*';
            } else {
                app()->instance(AiProviderPool::class, new ConfiguredAiProviderPool([]));
            }

            try {
                app(AiRegistryLifecycle::class)->activate(
                    new ActivateAgentVersionCommand(
                        actorId: (string) $fixture['admin']->id,
                        tenantId: (string) $fixture['tenant']->id,
                        scopeType: $scopeType,
                        scopeKey: $scopeKey,
                        agentVersionId: $candidate['agent_version_id'],
                        releaseDecisionId: $candidate['release_decision_id'],
                        rolloutPercentage: 100,
                        reason: "Reject static {$case} incompatibility",
                        expectedActivationId: $scopeType === 'tenant'
                            ? $fixture['activation_id']
                            : null,
                        expectedLockVersion: $scopeType === 'tenant' ? 0 : null,
                    ),
                );
                self::fail("Expected static {$case} incompatibility.");
            } catch (AiExecutionDeniedException $exception) {
                self::assertSame('evaluation_gate', $exception->reason);
            }
        }
    }

    public function test_activation_rejects_an_empty_output_schema_before_persisting(): void
    {
        $fixture = $this->fixture('empty-output-schema');
        $candidate = $this->eligibleCandidate($fixture);
        $configuration = json_decode(
            (string) DB::table('ai_agent_versions')
                ->where('id', $candidate['agent_version_id'])
                ->value('configuration'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $configuration['output_schema'] = '';
        DB::table('ai_agent_versions')
            ->where('id', $candidate['agent_version_id'])
            ->update(['configuration' => json_encode($configuration, JSON_THROW_ON_ERROR)]);

        try {
            app(AiRegistryLifecycle::class)->activate(new ActivateAgentVersionCommand(
                actorId: (string) $fixture['admin']->id,
                tenantId: (string) $fixture['tenant']->id,
                scopeType: 'tenant',
                scopeKey: (string) $fixture['tenant']->id,
                agentVersionId: $candidate['agent_version_id'],
                releaseDecisionId: $candidate['release_decision_id'],
                rolloutPercentage: 100,
                reason: 'Reject candidate without an output schema',
                expectedActivationId: $fixture['activation_id'],
                expectedLockVersion: 0,
            ));
            self::fail('Expected empty output schema rejection.');
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('evaluation_gate', $exception->reason);
        }

        self::assertSame(1, DB::table('ai_agent_activations')
            ->where('tenant_id', $fixture['tenant']->id)
            ->whereNull('effective_until')
            ->count());
    }

    public function test_activation_locks_the_complete_eligibility_graph_while_runtime_remains_read_only(): void
    {
        $fixture = $this->fixture('eligibility-locks');
        $candidate = $this->eligibleCandidate($fixture);
        $connection = DB::connection();
        $originalGrammar = $connection->getQueryGrammar();
        $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
        {
            protected function compileLock(Builder $query, $value)
            {
                return $value ? ' /* registry-for-update */' : '';
            }
        });
        $queries = [];
        $capture = true;
        DB::listen(static function (QueryExecuted $event) use (&$queries, &$capture): void {
            if ($capture && str_contains($event->sql, 'registry-for-update')) {
                $queries[] = $event->sql;
            }
        });

        try {
            $activationId = app(AiRegistryLifecycle::class)->activate(
                new ActivateAgentVersionCommand(
                    actorId: (string) $fixture['admin']->id,
                    tenantId: (string) $fixture['tenant']->id,
                    scopeType: 'tenant',
                    scopeKey: (string) $fixture['tenant']->id,
                    agentVersionId: $candidate['agent_version_id'],
                    releaseDecisionId: $candidate['release_decision_id'],
                    rolloutPercentage: 100,
                    reason: 'Lock complete eligibility graph before activation',
                    expectedActivationId: $fixture['activation_id'],
                    expectedLockVersion: 0,
                ),
            );
            foreach ([
                'tenants',
                'ai_release_decisions',
                'ai_release_policies',
                'ai_evaluation_runs',
                'ai_evaluation_versions',
                'ai_evaluation_suites',
                'ai_evaluation_cases',
                'ai_evaluation_results',
                'ai_agent_versions',
                'ai_agent_definitions',
                'ai_prompt_versions',
                'ai_prompt_definitions',
                'ai_model_versions',
                'ai_models',
                'ai_provider_versions',
                'ai_providers',
                'ai_agent_activations',
            ] as $table) {
                self::assertTrue(
                    collect($queries)->contains(
                        static fn (string $sql): bool => str_contains($sql, "\"{$table}\""),
                    ),
                    "Missing lock for {$table}.",
                );
            }
            $firstLock = static function (string $table) use ($queries): int {
                $index = collect($queries)->search(
                    static fn (string $sql): bool => str_contains($sql, "\"{$table}\""),
                );
                self::assertIsInt($index, "Missing ordered lock for {$table}.");

                return $index;
            };
            self::assertLessThan(
                $firstLock('ai_release_decisions'),
                $firstLock('tenants'),
                'The tenant lock must precede the release graph.',
            );
            self::assertLessThan(
                $firstLock('ai_agent_versions'),
                $firstLock('ai_release_decisions'),
                'The release decision graph must precede exact registry versions.',
            );
            self::assertLessThan(
                $firstLock('ai_agent_activations'),
                $firstLock('ai_agent_versions'),
                'Exact registry versions must precede activation/history locks.',
            );

            $capture = false;
            $queries = [];
            app(AiRegistryResolver::class)->resolve(
                $this->registryQuery($fixture, 'read-only-runtime'),
            );
            self::assertSame([], $queries);
            self::assertSame($activationId, DB::table('ai_agent_activations')
                ->whereNull('effective_until')
                ->value('id'));
        } finally {
            $capture = false;
            $connection->setQueryGrammar($originalGrammar);
        }
    }

    public function test_activation_rechecks_a_decision_revoked_between_eligibility_and_insert(): void
    {
        $fixture = $this->fixture('decision-revoked-between-phases');
        $candidate = $this->eligibleCandidate($fixture);
        $intercept = true;
        $mutated = false;
        DB::listen(static function (QueryExecuted $event) use (
            $candidate,
            $fixture,
            &$intercept,
            &$mutated,
        ): void {
            if (! $intercept
                || $mutated
                || ! str_contains($event->sql, 'ai_evaluation_results')
                || ! str_starts_with(strtolower(ltrim($event->sql)), 'select')) {
                return;
            }
            $mutated = true;
            DB::table('ai_release_decisions')
                ->where('id', $candidate['release_decision_id'])
                ->update([
                    'revoked_at' => now(),
                    'revoked_by' => (string) $fixture['admin']->id,
                ]);
        });

        try {
            app(AiRegistryLifecycle::class)->activate(new ActivateAgentVersionCommand(
                actorId: (string) $fixture['admin']->id,
                tenantId: (string) $fixture['tenant']->id,
                scopeType: 'tenant',
                scopeKey: (string) $fixture['tenant']->id,
                agentVersionId: $candidate['agent_version_id'],
                releaseDecisionId: $candidate['release_decision_id'],
                rolloutPercentage: 100,
                reason: 'Reject revocation injected between transaction phases',
                expectedActivationId: $fixture['activation_id'],
                expectedLockVersion: 0,
            ));
            self::fail('Expected between-phase decision revocation rejection.');
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('evaluation_gate', $exception->reason);
        } finally {
            $intercept = false;
        }

        self::assertTrue($mutated);
        self::assertSame($fixture['activation_id'], DB::table('ai_agent_activations')
            ->whereNull('effective_until')
            ->value('id'));
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
            marketCode: 'HN',
            residencyClass: 'HN',
            actorId: (string) $fixture['admin']->id,
            cohortSubject: (string) $fixture['admin']->id,
        ));
    }

    public function test_retirement_rejects_direct_and_transitive_open_assignment_dependencies_including_canary_roots(): void
    {
        $fixture = $this->fixture('retirement-guard');
        $lifecycle = app(AiRegistryLifecycle::class);
        foreach ([
            'provider' => $fixture['selection']->providerVersionId,
            'model' => $fixture['selection']->modelVersionId,
            'prompt' => $fixture['selection']->promptVersionId,
            'agent' => $fixture['selection']->agentVersionId,
        ] as $versionType => $versionId) {
            try {
                $lifecycle->promote(new PromoteAiVersionCommand(
                    actorId: (string) $fixture['admin']->id,
                    tenantId: (string) $fixture['tenant']->id,
                    versionType: $versionType,
                    versionId: $versionId,
                    targetStatus: 'retired',
                    releaseDecisionId: null,
                    reason: "Attempt to retire assigned {$versionType} dependency.",
                ));
                self::fail("Expected assigned {$versionType} retirement rejection.");
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('target_status', $exception->errors());
            }
            $this->assertDatabaseHas("ai_{$versionType}_versions", [
                'id' => $versionId,
                'lifecycle_status' => 'active',
            ]);
        }

        $candidate = $this->eligibleCandidate($fixture);
        $canaryId = $lifecycle->activate(new ActivateAgentVersionCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            scopeType: 'tenant',
            scopeKey: (string) $fixture['tenant']->id,
            agentVersionId: $candidate['agent_version_id'],
            releaseDecisionId: $candidate['release_decision_id'],
            rolloutPercentage: 25,
            reason: 'Keep the prior release as a canary control root.',
            expectedActivationId: $fixture['activation_id'],
            expectedLockVersion: 0,
        ));
        try {
            $lifecycle->promote(new PromoteAiVersionCommand(
                actorId: (string) $fixture['admin']->id,
                tenantId: (string) $fixture['tenant']->id,
                versionType: 'agent',
                versionId: $fixture['selection']->agentVersionId,
                targetStatus: 'retired',
                releaseDecisionId: null,
                reason: 'Attempt to retire the live canary fallback root.',
            ));
            self::fail('Expected canary fallback root retirement rejection.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('target_status', $exception->errors());
        }

        $replacementId = $lifecycle->activate(new ActivateAgentVersionCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            scopeType: 'tenant',
            scopeKey: (string) $fixture['tenant']->id,
            agentVersionId: $candidate['agent_version_id'],
            releaseDecisionId: $candidate['release_decision_id'],
            rolloutPercentage: 100,
            reason: 'Replace the canary and release its closed control root.',
            expectedActivationId: $canaryId,
            expectedLockVersion: 0,
        ));
        self::assertNotSame($canaryId, $replacementId);
        $lifecycle->promote(new PromoteAiVersionCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            versionType: 'agent',
            versionId: $fixture['selection']->agentVersionId,
            targetStatus: 'retired',
            releaseDecisionId: null,
            reason: 'Retire the fully replaced closed control root.',
        ));
        $this->assertDatabaseHas('ai_agent_versions', [
            'id' => $fixture['selection']->agentVersionId,
            'lifecycle_status' => 'retired',
        ]);
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

    public function test_a_complete_draft_candidate_set_becomes_eligible_atomically_and_resolves_after_activation(): void
    {
        $fixture = $this->fixture('candidate-set');
        $lifecycle = app(AiRegistryLifecycle::class);
        $tenantId = (string) $fixture['tenant']->id;
        $actorId = (string) $fixture['admin']->id;
        $currentAgent = DB::table('ai_agent_versions')
            ->where('id', $fixture['selection']->agentVersionId)
            ->first();
        $currentModel = DB::table('ai_model_versions')
            ->where('id', $fixture['selection']->modelVersionId)
            ->first();
        $currentProvider = DB::table('ai_provider_versions')
            ->where('id', $fixture['selection']->providerVersionId)
            ->first();
        $currentPrompt = DB::table('ai_prompt_versions')
            ->where('id', $fixture['selection']->promptVersionId)
            ->first();
        self::assertNotNull($currentAgent);
        self::assertNotNull($currentModel);
        self::assertNotNull($currentProvider);
        self::assertNotNull($currentPrompt);

        $providerVersionId = $lifecycle->createDraft(new CreateAiDraftCommand(
            actorId: $actorId,
            tenantId: $tenantId,
            versionType: 'provider',
            definitionId: (string) $currentProvider->provider_id,
            payload: [
                'secret_reference' => 'fake.primary',
                'configuration' => [
                    'enabled' => true,
                    'capabilities' => ['structured_output'],
                    'allowed_market_country_codes' => ['*'],
                    'residency_country_codes' => ['HN'],
                ],
            ],
            reason: 'Create governed provider candidate draft',
        ));
        $modelVersionId = $lifecycle->createDraft(new CreateAiDraftCommand(
            actorId: $actorId,
            tenantId: $tenantId,
            versionType: 'model',
            definitionId: (string) $currentModel->model_id,
            payload: [
                'provider_version_id' => $providerVersionId,
                'context_window' => 32_000,
                'input_cost_minor' => 20,
                'output_cost_minor' => 80,
                'currency' => 'USD',
                'configuration' => [
                    'capabilities' => ['structured_output'],
                    'allowed_market_country_codes' => ['*'],
                    'residency_country_codes' => ['HN'],
                ],
            ],
            reason: 'Create governed model candidate draft',
        ));
        $promptVersionId = $lifecycle->createDraft(new CreateAiDraftCommand(
            actorId: $actorId,
            tenantId: $tenantId,
            versionType: 'prompt',
            definitionId: (string) $currentPrompt->prompt_definition_id,
            payload: [
                'template' => 'Governed replacement prompt.',
                'input_schema' => [],
            ],
            reason: 'Create governed prompt candidate draft',
        ));
        $agentVersionId = $lifecycle->createDraft(new CreateAiDraftCommand(
            actorId: $actorId,
            tenantId: $tenantId,
            versionType: 'agent',
            definitionId: (string) $currentAgent->agent_definition_id,
            payload: [
                'model_version_id' => $modelVersionId,
                'configuration' => [
                    'capability' => 'private_agent',
                    'audience' => 'private',
                    'channels' => ['*'],
                    'model_version_ids' => [$modelVersionId],
                    'prompt_version_id' => $promptVersionId,
                    'output_schema' => 'private_agent.answer.v1',
                    'required_capabilities' => ['structured_output'],
                    'max_output_tokens' => 600,
                ],
            ],
            reason: 'Create governed agent candidate draft',
        ));

        foreach ([
            'provider' => $providerVersionId,
            'prompt' => $promptVersionId,
            'model' => $modelVersionId,
            'agent' => $agentVersionId,
        ] as $versionType => $versionId) {
            $lifecycle->promote(new PromoteAiVersionCommand(
                actorId: $actorId,
                tenantId: $tenantId,
                versionType: $versionType,
                versionId: $versionId,
                targetStatus: 'candidate',
                releaseDecisionId: null,
                reason: "Freeze {$versionType} candidate for evaluation",
            ));
        }

        $decisionId = $this->releaseDecision(
            $fixture,
            $agentVersionId,
            $promptVersionId,
            $providerVersionId,
            $modelVersionId,
        );
        $lifecycle->promote(new PromoteAiVersionCommand(
            actorId: $actorId,
            tenantId: $tenantId,
            versionType: 'agent',
            versionId: $agentVersionId,
            targetStatus: 'eligible',
            releaseDecisionId: $decisionId,
            reason: 'Promote the exact evaluated candidate set atomically',
        ));

        foreach ([
            'ai_provider_versions' => $providerVersionId,
            'ai_model_versions' => $modelVersionId,
            'ai_prompt_versions' => $promptVersionId,
            'ai_agent_versions' => $agentVersionId,
        ] as $table => $versionId) {
            $this->assertDatabaseHas($table, [
                'id' => $versionId,
                'lifecycle_status' => 'eligible',
            ]);
        }

        $activationId = $lifecycle->activate(new ActivateAgentVersionCommand(
            actorId: $actorId,
            tenantId: $tenantId,
            scopeType: 'tenant',
            scopeKey: $tenantId,
            agentVersionId: $agentVersionId,
            releaseDecisionId: $decisionId,
            rolloutPercentage: 100,
            reason: 'Activate the fully governed replacement release',
            expectedActivationId: $fixture['activation_id'],
            expectedLockVersion: 0,
        ));
        foreach ([
            'ai_provider_versions' => $providerVersionId,
            'ai_model_versions' => $modelVersionId,
            'ai_prompt_versions' => $promptVersionId,
            'ai_agent_versions' => $agentVersionId,
        ] as $table => $versionId) {
            DB::table($table)->where('id', $versionId)->update(['released_at' => null]);
        }

        $resolved = app(AiRegistryResolver::class)->resolve(
            $this->registryQuery($fixture, 'governed-release-subject'),
        );
        self::assertSame($agentVersionId, $resolved->agentVersionId);
        self::assertSame($activationId, DB::table('ai_agent_activations')
            ->whereNull('effective_until')
            ->where('agent_version_id', $resolved->agentVersionId)
            ->value('id'));
    }

    public function test_candidate_set_must_be_intrinsically_compatible_before_eligibility_promotion(): void
    {
        foreach ([
            'empty_schema',
            'zero_tokens',
            'policy_capability',
            'deployment',
        ] as $case) {
            $fixture = $this->fixture('eligible-intrinsic-'.$case);
            $candidate = $this->eligibleCandidate($fixture, 'candidate');
            $agentConfiguration = json_decode(
                (string) DB::table('ai_agent_versions')
                    ->where('id', $candidate['agent_version_id'])
                    ->value('configuration'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            match ($case) {
                'empty_schema' => $agentConfiguration['output_schema'] = '',
                'zero_tokens' => $agentConfiguration['max_output_tokens'] = 0,
                'policy_capability' => DB::table('ai_release_policies')
                    ->where('id', DB::table('ai_release_decisions')
                        ->where('id', $candidate['release_decision_id'])
                        ->value('release_policy_id'))
                    ->update(['rules' => json_encode([
                        'allowed_market_country_codes' => ['HN'],
                        'residency_country_codes' => ['HN'],
                        'required_capabilities' => ['structured_output', 'moderation'],
                    ], JSON_THROW_ON_ERROR)]),
                'deployment' => app()->instance(
                    AiProviderPool::class,
                    new ConfiguredAiProviderPool([]),
                ),
            };
            if (in_array($case, ['empty_schema', 'zero_tokens'], true)) {
                DB::table('ai_agent_versions')
                    ->where('id', $candidate['agent_version_id'])
                    ->update(['configuration' => json_encode(
                        $agentConfiguration,
                        JSON_THROW_ON_ERROR,
                    )]);
            }

            try {
                app(AiRegistryLifecycle::class)->promote(new PromoteAiVersionCommand(
                    actorId: (string) $fixture['admin']->id,
                    tenantId: (string) $fixture['tenant']->id,
                    versionType: 'agent',
                    versionId: $candidate['agent_version_id'],
                    targetStatus: 'eligible',
                    releaseDecisionId: $candidate['release_decision_id'],
                    reason: 'Reject intrinsically incompatible candidate set',
                ));
                self::fail("Expected intrinsic candidate incompatibility for {$case}.");
            } catch (AiExecutionDeniedException $exception) {
                self::assertSame('evaluation_gate', $exception->reason);
            }

            $this->assertDatabaseHas('ai_agent_versions', [
                'id' => $candidate['agent_version_id'],
                'lifecycle_status' => 'candidate',
            ]);
        }
    }

    public function test_prompt_draft_checksum_and_ciphertext_rotate_with_the_normalized_key_version(): void
    {
        $fixture = $this->fixture('prompt-key-rotation');
        $definitionId = (string) DB::table('ai_prompt_versions')
            ->where('id', $fixture['selection']->promptVersionId)
            ->value('prompt_definition_id');
        $command = [
            'actorId' => (string) $fixture['admin']->id,
            'tenantId' => (string) $fixture['tenant']->id,
            'versionType' => 'prompt',
            'definitionId' => $definitionId,
            'payload' => [
                'template' => 'Stable prompt body for key rotation.',
                'input_schema' => ['type' => 'object'],
            ],
            'reason' => 'Create key rotation prompt regression draft',
        ];
        config()->set('ai.execution_retention.encryption_key_version', ' V1 ');
        $firstId = app(AiRegistryLifecycle::class)->createDraft(
            new CreateAiDraftCommand(...$command),
        );
        config()->set('ai.execution_retention.encryption_key_version', 'v2');
        $secondId = app(AiRegistryLifecycle::class)->createDraft(
            new CreateAiDraftCommand(...$command),
        );

        self::assertNotSame($firstId, $secondId);
        $first = DB::table('ai_prompt_versions')->where('id', $firstId)->first();
        $second = DB::table('ai_prompt_versions')->where('id', $secondId)->first();
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('v1', $first->encryption_key_version);
        self::assertSame('v2', $second->encryption_key_version);
        self::assertNotSame($first->checksum, $second->checksum);
        self::assertNotSame($first->template_ciphertext, $second->template_ciphertext);
    }

    public function test_registry_drafts_and_reasons_reject_deep_credentials_before_audit_or_outbox(): void
    {
        $fixture = $this->fixture('secret-safety');
        $providerDefinitionId = (string) DB::table('ai_provider_versions')
            ->where('id', $fixture['selection']->providerVersionId)
            ->value('provider_id');
        $activityCount = DB::table('activity_log')->count();
        $outboxCount = DB::table('outbox_messages')->count();
        $versionCount = DB::table('ai_provider_versions')->count();
        $credentials = [
            ['token' => 'opaque'],
            ['nested' => ['authorization' => 'Basic value']],
            ['private_key' => 'opaque'],
            ['access_key' => 'opaque'],
            ['client_secret' => 'opaque'],
            ['api_key' => 'opaque'],
            ['clientSecret' => 'opaque'],
            ['apiKey' => 'opaque'],
            ['accessKey' => 'opaque'],
            ['privateKey' => 'opaque'],
            ['authToken' => 'opaque'],
            ['accessToken' => 'opaque'],
            ['password' => 'opaque'],
            ['secret' => 'opaque'],
            ['bearer' => 'opaque'],
            ['credential' => 'short'],
            ['value' => 'password = Hunter2!'],
            ['value' => 'token: abc'],
            ['value' => 'credential: x'],
            ['value' => 'authorization = Basic short'],
            ['value' => 'private_key: x'],
            ['value' => 'access_key = x'],
            ['value' => 'client_secret: x'],
            ['value' => 'api_key = x'],
            ['value' => 'secret: x'],
            ['value' => 'bearer = x'],
            ['value' => 'sk-project-1234567890abcdef'],
            ['value' => 'AKIAIOSFODNN7EXAMPLE'],
            ['value' => "-----BEGIN PRIVATE KEY-----\nfixture\n-----END PRIVATE KEY-----"],
            ['value' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJmaXh0dXJlIn0.signaturefixture'],
            ['value' => 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789+/AbCdEf'],
            ['value' => 'https://operator:Sup3rSecret@example.test/v1'],
            ['value' => 'https://api.example.test/v1?apiKey=sk-project-1234567890abcdef'],
            ['value' => 'https://api.example.test/v1?token=abc'],
            ['value' => 'operation=rotate;credential=y'],
            ['value' => rawurlencode('clientSecret=encoded-secret-value')],
            ['value' => base64_encode('apiKey=encoded-secret-value')],
            ['value' => base64_encode('accessToken=abc')],
        ];

        foreach ($credentials as $index => $credential) {
            try {
                app(AiRegistryLifecycle::class)->createDraft(new CreateAiDraftCommand(
                    actorId: (string) $fixture['admin']->id,
                    tenantId: (string) $fixture['tenant']->id,
                    versionType: 'provider',
                    definitionId: $providerDefinitionId,
                    payload: [
                        'secret_reference' => 'fake.primary',
                        'configuration' => ['enabled' => true, 'nested' => $credential],
                    ],
                    reason: "Reject credential-shaped provider payload {$index}",
                ));
                self::fail("Expected credential payload {$index} rejection.");
            } catch (ValidationException $exception) {
                self::assertStringNotContainsString(
                    (string) collect($credential)->flatten()->first(),
                    $exception->getMessage(),
                );
            }
        }

        try {
            app(AiRegistryLifecycle::class)->createDraft(new CreateAiDraftCommand(
                actorId: (string) $fixture['admin']->id,
                tenantId: (string) $fixture['tenant']->id,
                versionType: 'provider',
                definitionId: $providerDefinitionId,
                payload: [
                    'secret_reference' => 'sk-project-1234567890abcdef',
                    'configuration' => ['enabled' => true],
                ],
                reason: 'Reject inline provider secret reference',
            ));
            self::fail('Expected inline secret reference rejection.');
        } catch (ValidationException $exception) {
            self::assertStringNotContainsString('sk-project-1234567890abcdef', $exception->getMessage());
        }

        self::assertSame($activityCount, DB::table('activity_log')->count());
        self::assertSame($outboxCount, DB::table('outbox_messages')->count());
        self::assertSame($versionCount, DB::table('ai_provider_versions')->count());

        $draftId = app(AiRegistryLifecycle::class)->createDraft(new CreateAiDraftCommand(
            actorId: (string) $fixture['admin']->id,
            tenantId: (string) $fixture['tenant']->id,
            versionType: 'provider',
            definitionId: $providerDefinitionId,
            payload: [
                'secret_reference' => 'fake.rotated',
                'configuration' => [
                    'enabled' => false,
                    'notes' => 'Credential rotation policy reviewed without inline material.',
                    'max_output_tokens' => 640,
                ],
            ],
            reason: 'Create safe draft before unsafe reason test',
        ));
        $activityCount = DB::table('activity_log')->count();
        $outboxCount = DB::table('outbox_messages')->count();
        foreach ([
            'Rotate with sk-project-1234567890abcdef',
            'Emergency rollback password = Hunter2!',
            'Hotfix authorization: Basic short',
            'Operator credential: x',
        ] as $unsafeReason) {
            try {
                app(AiRegistryLifecycle::class)->promote(new PromoteAiVersionCommand(
                    actorId: (string) $fixture['admin']->id,
                    tenantId: (string) $fixture['tenant']->id,
                    versionType: 'provider',
                    versionId: $draftId,
                    targetStatus: 'candidate',
                    releaseDecisionId: null,
                    reason: $unsafeReason,
                ));
                self::fail('Expected a credential-shaped reason rejection.');
            } catch (ValidationException $exception) {
                self::assertStringNotContainsString($unsafeReason, $exception->getMessage());
            }
        }
        self::assertSame($activityCount, DB::table('activity_log')->count());
        self::assertSame($outboxCount, DB::table('outbox_messages')->count());
        $this->assertDatabaseHas('ai_provider_versions', [
            'id' => $draftId,
            'lifecycle_status' => 'draft',
        ]);
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
        app()->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            $selection->adapterKey => [
                $selection->secretReference => new FakeAiProvider,
            ],
        ]));
        $activationId = (string) DB::table('ai_agent_activations')
            ->where('tenant_id', $tenant->id)
            ->where('agent_version_id', $selection->agentVersionId)
            ->value('id');

        return compact('admin', 'tenant', 'selection', 'activationId')
            + ['activation_id' => $activationId];
    }

    /**
     * @param  array{admin:User,tenant:Tenant,selection:object,activation_id:string}  $fixture
     */
    private function registryQuery(array $fixture, string $subject): AiRegistryQuery
    {
        return new AiRegistryQuery(
            tenantId: (string) $fixture['tenant']->id,
            audience: 'private',
            capability: 'private_agent',
            channel: 'web',
            marketCode: 'HN',
            residencyClass: 'HN',
            actorId: (string) $fixture['admin']->id,
            cohortSubject: $subject,
        );
    }

    /** @return array{string, string} */
    private function cohortSubjects(string $salt, int $rolloutPercentage): array
    {
        $canary = null;
        $control = null;
        for ($index = 0; $index < 10_000 && ($canary === null || $control === null); $index++) {
            $subject = "subject-{$index}";
            $bucket = hexdec(substr(hash('sha256', $salt.'|'.$subject), 0, 8)) % 100;
            if ($bucket < $rolloutPercentage) {
                $canary ??= $subject;
            } else {
                $control ??= $subject;
            }
        }

        self::assertNotNull($canary);
        self::assertNotNull($control);

        return [$canary, $control];
    }

    /**
     * @param  array{admin:User,tenant:Tenant,selection:object,activation_id:string}  $fixture
     * @return array{agent_version_id:string,release_decision_id:string,evaluation_run_id:string}
     */
    private function eligibleCandidate(array $fixture, string $lifecycleStatus = 'eligible'): array
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
            'lifecycle_status' => $lifecycleStatus,
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
                'completed_at' => now()->subSeconds(2),
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

    /**
     * @param  array{admin:User,tenant:Tenant,selection:object,activation_id:string}  $fixture
     */
    private function releaseDecision(
        array $fixture,
        string $agentVersionId,
        string $promptVersionId,
        string $providerVersionId,
        string $modelVersionId,
    ): string {
        $tenantId = (string) $fixture['tenant']->id;
        $evaluationVersionId = (string) DB::table('ai_evaluation_runs')
            ->where('agent_version_id', $fixture['selection']->agentVersionId)
            ->value('evaluation_version_id');
        $evaluationRunId = (string) Str::uuid();
        DB::table('ai_evaluation_runs')->insert([
            'id' => $evaluationRunId,
            'tenant_id' => $tenantId,
            'evaluation_version_id' => $evaluationVersionId,
            'agent_version_id' => $agentVersionId,
            'prompt_version_id' => $promptVersionId,
            'provider_version_id' => $providerVersionId,
            'model_version_id' => $modelVersionId,
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
                'tenant_id' => $tenantId,
                'evaluation_run_id' => $evaluationRunId,
                'evaluation_case_id' => $caseId,
                'status' => 'passed',
                'score_minor' => 100,
                'completed_at' => now()->subSeconds(2),
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
            'tenant_id' => $tenantId,
            'release_policy_id' => $policyId,
            'evaluation_run_id' => $evaluationRunId,
            'agent_version_id' => $agentVersionId,
            'decision' => 'passed',
            'decided_by' => (string) $fixture['admin']->id,
            'evidence_hash' => hash('sha256', $releaseDecisionId),
            'rationale' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'decided_at' => now(),
            'candidate_checksum' => (string) DB::table('ai_agent_versions')
                ->where('id', $agentVersionId)
                ->value('checksum'),
            'valid_from' => now()->subSecond(),
            'valid_until' => now()->addDay(),
            'revoked_at' => null,
            'revoked_by' => null,
            'revocation_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $releaseDecisionId;
    }
}
