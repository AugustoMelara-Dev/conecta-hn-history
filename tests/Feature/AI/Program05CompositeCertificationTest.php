<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\Conversation\AiFoundationBackfill;
use App\Application\Ai\Registry\AiRegistryLifecycle;
use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Registry\Commands\ActivateAgentVersionCommand;
use App\Application\Ai\Registry\Commands\RollbackAgentActivationCommand;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\Support\AI\Program05CompositeCertificateFixture;
use Tests\Support\AI\Program05CompositeCertificateReport;
use Tests\TestCase;

final class Program05CompositeCertificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_program05_seams_complete_the_local_composite_sequence_without_contracting(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('sqlite', (string) config('database.default'));
        self::assertSame('true', strtolower((string) getenv('DOVRA_PROGRAM05_COMPOSITE_ALLOW')));
        self::assertSame('sqlite', (string) getenv('DB_CONNECTION'));
        $this->assertGuardRejectsMismatchedEffectiveDatabase((string) getenv('DOVRA_PROGRAM05_COMPOSITE_DATABASE'));

        $fixture = Program05CompositeCertificateFixture::create();
        $provider = $fixture['provider'];
        self::assertInstanceOf(FakeAiProvider::class, $provider);
        self::assertInstanceOf(ConfiguredAiProviderPool::class, app(AiProviderPool::class));
        self::assertSame(0, count($provider->requests));

        $tenantId = (string) $fixture['tenant_id'];
        $foreignTenantId = (string) $fixture['foreign_tenant_id'];
        $root = $fixture['root'];
        $candidate = $fixture['candidate'];
        $expectedExecutions = (int) $fixture['expected_legacy_count'];
        $expectedConversations = (int) $fixture['expected_conversation_count'];
        self::assertGreaterThanOrEqual(5, $expectedExecutions);
        self::assertGreaterThanOrEqual(5, $expectedConversations);

        $foreignBaseline = $this->foreignState($foreignTenantId);
        $this->assertForeignState($foreignTenantId, $foreignBaseline);
        $this->assertNoEligibleForeignRoute($foreignTenantId);
        $baselineSafety = $this->sideEffectCounts();
        self::assertSame(0, $baselineSafety['channel_delivery_rows']);
        $baselineOutboxCount = $this->tableCount('outbox_messages');
        $legacyBefore = Program05CompositeCertificateFixture::legacyState($tenantId);
        $canonicalBefore = Program05CompositeCertificateFixture::canonicalState($tenantId);

        $openRoot = DB::table('ai_agent_activations')
            ->where('tenant_id', $tenantId)
            ->whereNull('effective_until')
            ->get();
        self::assertCount(1, $openRoot);
        self::assertSame((string) $root['activation_id'], (string) $openRoot->first()->id);
        self::assertSame(0, (int) $openRoot->first()->lock_version);

        // 1. Expand.
        $expand = app(AiFoundationBackfill::class)->run(2, phase: 'expand')->toArray();
        self::assertSame('expand', $expand['phase']);
        self::assertTrue($expand['validated']);
        self::assertFalse($expand['has_more']);
        self::assertSame(0, (int) $expand['gaps']);
        self::assertSame(0, (int) $expand['conflicts']);
        self::assertSame('expand_ready', $expand['contract_status']);
        self::assertSame(0, count($provider->requests));
        $this->assertForeignState($foreignTenantId, $foreignBaseline);

        $checkpointDirectory = sys_get_temp_dir().'\program05-composite-'.str_replace('-', '', (string) Str::uuid());
        if (! mkdir($checkpointDirectory, 0700, true) && ! is_dir($checkpointDirectory)) {
            self::fail('Unable to create the Program 05 checkpoint directory.');
        }

        try {
            // 2. Paginated backfill/resume.
            $backfillPath = $checkpointDirectory.DIRECTORY_SEPARATOR.'backfill.json';
            $backfillBatches = $this->runBatches('backfill', $backfillPath);
            $this->assertResumedBatches(
                $backfillBatches,
                expectedMinimum: 3,
                expectedExecutions: $expectedExecutions,
                expectedConversations: $expectedConversations,
                finalStatus: 'exhausted',
            );
            $backfillCanonical = Program05CompositeCertificateFixture::canonicalState($tenantId);
            $backfillLegacy = Program05CompositeCertificateFixture::legacyState($tenantId);
            $backfillCounts = $this->tenantCounts($tenantId);
            $backfillIdempotentPath = $checkpointDirectory.DIRECTORY_SEPARATOR.'backfill-idempotent.json';
            $this->runBatches('backfill', $backfillIdempotentPath);
            self::assertSame($backfillCanonical, Program05CompositeCertificateFixture::canonicalState($tenantId));
            self::assertSame($backfillLegacy, Program05CompositeCertificateFixture::legacyState($tenantId));
            self::assertSame($backfillCounts, $this->tenantCounts($tenantId));
            self::assertSame(0, count($provider->requests));
            $this->assertForeignState($foreignTenantId, $foreignBaseline);

            // 3. Independent paginated validate, with an exact read-only snapshot.
            $validateBefore = $this->exactSnapshot($tenantId);
            $validatePath = $checkpointDirectory.DIRECTORY_SEPARATOR.'validate.json';
            $validateBatches = $this->runBatches('validate', $validatePath);
            $this->assertResumedBatches(
                $validateBatches,
                expectedMinimum: 3,
                expectedExecutions: $expectedExecutions,
                expectedConversations: $expectedConversations,
                finalStatus: 'validated',
            );
            self::assertSame($validateBefore, $this->exactSnapshot($tenantId));
            self::assertSame(0, count($provider->requests));
            $this->assertForeignState($foreignTenantId, $foreignBaseline);

            // 4. Read-only shadow through the resolver.
            $shadowBefore = $this->exactSnapshot($tenantId);
            $shadowQuery = new AiRegistryQuery(
                tenantId: $tenantId,
                audience: 'private',
                capability: 'private_tenant_agent',
                channel: 'web',
                marketCode: 'HN',
                residencyClass: 'HN',
                actorId: (string) $fixture['actor_id'],
                cohortSubject: 'program05-shadow-subject',
            );
            $shadowOne = app(AiRegistryResolver::class)->resolve($shadowQuery);
            $shadowTwo = app(AiRegistryResolver::class)->resolve($shadowQuery);
            self::assertSame($shadowOne->providerVersionId, $shadowTwo->providerVersionId);
            self::assertSame($shadowOne->modelVersionId, $shadowTwo->modelVersionId);
            self::assertSame($shadowOne->agentVersionId, $shadowTwo->agentVersionId);
            self::assertSame($shadowOne->promptVersionId, $shadowTwo->promptVersionId);
            self::assertSame((string) $root['provider_version_id'], $shadowOne->providerVersionId);
            self::assertSame((string) $root['model_version_id'], $shadowOne->modelVersionId);
            self::assertSame((string) $root['agent_version_id'], $shadowOne->agentVersionId);
            self::assertSame((string) $root['prompt_version_id'], $shadowOne->promptVersionId);
            $legacyExecution = DB::table('ai_executions')->where('tenant_id', $tenantId)->first();
            self::assertSame((string) $root['provider_version_id'], (string) $legacyExecution->provider_version_id);
            self::assertSame((string) $root['model_version_id'], (string) $legacyExecution->model_version_id);
            self::assertSame((string) $root['agent_version_id'], (string) $legacyExecution->agent_version_id);
            self::assertSame((string) $root['prompt_version_id'], (string) $legacyExecution->prompt_version_id);
            self::assertSame($shadowBefore, $this->exactSnapshot($tenantId));
            self::assertSame(0, count($provider->requests));
            $this->assertForeignState($foreignTenantId, $foreignBaseline);

            // 5. Governed 25% canary and sticky paths.
            $activationId = app(AiRegistryLifecycle::class)->activate(new ActivateAgentVersionCommand(
                actorId: (string) $fixture['actor_id'],
                tenantId: $tenantId,
                scopeType: 'tenant',
                scopeKey: $tenantId,
                agentVersionId: (string) $candidate['agent_version_id'],
                releaseDecisionId: (string) $candidate['release_decision_id'],
                rolloutPercentage: 25,
                reason: 'Program 05 composite local canary.',
                expectedActivationId: (string) $root['activation_id'],
                expectedLockVersion: 0,
            ));
            $candidateActivation = DB::table('ai_agent_activations')
                ->where('tenant_id', $tenantId)
                ->where('id', $activationId)
                ->whereNull('effective_until')
                ->first();
            self::assertNotNull($candidateActivation);
            self::assertSame(25, (int) $candidateActivation->rollout_percentage);
            self::assertSame((string) $root['activation_id'], (string) $candidateActivation->previous_activation_id);
            self::assertSame(0, (int) $candidateActivation->lock_version);
            self::assertCount(1, DB::table('ai_agent_activations')->where('tenant_id', $tenantId)->whereNull('effective_until')->get());
            $subjects = Program05CompositeCertificateFixture::cohortSubjects((string) $candidateActivation->cohort_salt, 25);
            $canaryQuery = $this->registryQuery($tenantId, (string) $fixture['actor_id'], $subjects['canary_subject']);
            $controlQuery = $this->registryQuery($tenantId, (string) $fixture['actor_id'], $subjects['control_subject']);
            $resolver = app(AiRegistryResolver::class);
            $canarySelection = $resolver->resolve($canaryQuery);
            $controlSelection = $resolver->resolve($controlQuery);
            self::assertSame((string) $candidate['agent_version_id'], $canarySelection->agentVersionId);
            self::assertSame((string) $root['agent_version_id'], $controlSelection->agentVersionId);
            $backfill = app(AiFoundationBackfill::class);
            $canaryConversation = (string) $fixture['conversations']['canary'];
            $controlConversation = (string) $fixture['conversations']['control'];
            $backfill->persistCanaryPath($tenantId, $canaryConversation, $canarySelection->agentVersionId);
            $backfill->persistCanaryPath($tenantId, $canaryConversation, $canarySelection->agentVersionId);
            $backfill->persistCanaryPath($tenantId, $controlConversation, $controlSelection->agentVersionId);
            $backfill->persistCanaryPath($tenantId, $controlConversation, $controlSelection->agentVersionId);
            self::assertSame($canarySelection->agentVersionId, $backfill->pinnedAgentVersionId($tenantId, $canaryConversation));
            self::assertSame($controlSelection->agentVersionId, $backfill->pinnedAgentVersionId($tenantId, $controlConversation));
            self::assertSame($canarySelection->agentVersionId, $resolver->resolvePinned($canaryQuery, $canarySelection->agentVersionId)->agentVersionId);
            self::assertSame($controlSelection->agentVersionId, $resolver->resolvePinned($controlQuery, $controlSelection->agentVersionId)->agentVersionId);
            self::assertSame(0, count($provider->requests));
            $this->assertForeignState($foreignTenantId, $foreignBaseline);

            // 6. Governed rollback and fail-closed candidate pin.
            $rollbackId = app(AiRegistryLifecycle::class)->rollback(new RollbackAgentActivationCommand(
                actorId: (string) $fixture['actor_id'],
                tenantId: $tenantId,
                activationId: (string) $activationId,
                expectedLockVersion: 0,
                reason: 'Program 05 composite rollback proof.',
            ));
            $rollbackActivation = DB::table('ai_agent_activations')
                ->where('tenant_id', $tenantId)
                ->where('id', $rollbackId)
                ->whereNull('effective_until')
                ->first();
            self::assertNotNull($rollbackActivation);
            self::assertSame((string) $root['agent_version_id'], (string) $rollbackActivation->agent_version_id);
            self::assertSame(100, (int) $rollbackActivation->rollout_percentage);
            self::assertSame((string) $activationId, (string) $rollbackActivation->previous_activation_id);
            self::assertSame((string) $root['agent_version_id'], $resolver->resolve($canaryQuery)->agentVersionId);
            self::assertSame((string) $root['agent_version_id'], $resolver->resolve($controlQuery)->agentVersionId);
            self::assertSame((string) $root['agent_version_id'], $resolver->resolvePinned($controlQuery, (string) $root['agent_version_id'])->agentVersionId);
            $candidateDenied = false;
            try {
                $resolver->resolvePinned($canaryQuery, (string) $candidate['agent_version_id']);
            } catch (AiExecutionDeniedException $exception) {
                $candidateDenied = $exception->reason === 'registry_selection';
            }
            self::assertTrue($candidateDenied);
            self::assertSame((string) $candidate['agent_version_id'], $backfill->pinnedAgentVersionId($tenantId, $canaryConversation));
            self::assertSame(2, $this->tableCount('outbox_messages') - $baselineOutboxCount);
            $newOutboxTypes = DB::table('outbox_messages')
                ->orderBy('created_at')
                ->pluck('type')
                ->all();
            self::assertNotEmpty($newOutboxTypes);
            self::assertSame([
                'App\\Domain\\Ai\\Events\\AiRegistryChanged',
            ], array_values(array_unique(array_slice($newOutboxTypes, -2))));
            self::assertSame(0, count($provider->requests));
            $this->assertForeignState($foreignTenantId, $foreignBaseline);

            // 7. Contract remains blocked and legacy state is preserved.
            $legacyContractBefore = Program05CompositeCertificateFixture::legacyState($tenantId);
            $canonicalContractBefore = Program05CompositeCertificateFixture::canonicalState($tenantId);
            $contract = app(AiFoundationBackfill::class)->run(2, phase: 'contract')->toArray();
            self::assertSame('contract', $contract['phase']);
            self::assertFalse($contract['validated']);
            self::assertSame('deferred_until_consumers_proven', $contract['contract_status']);
            self::assertSame($legacyContractBefore, Program05CompositeCertificateFixture::legacyState($tenantId));
            self::assertSame($canonicalContractBefore, Program05CompositeCertificateFixture::canonicalState($tenantId));
            self::assertSame(0, count($provider->requests));
            $this->assertForeignState($foreignTenantId, $foreignBaseline);

            $safety = $this->sideEffectCounts();
            self::assertSame($baselineSafety, $safety);
            self::assertSame(0, $safety['channel_delivery_rows']);
            $facts = [
                'schema' => 1,
                'status' => 'LOCALLY_VERIFIED',
                'decision' => 'NO_CONTRACT',
                'scope' => 'disposable_sqlite',
                'sequence' => ['expand', 'backfill', 'validate', 'shadow', 'canary', 'rollback', 'contract'],
                'expand' => [
                    'phase' => 'expand',
                    'validated' => true,
                    'has_more' => false,
                    'gaps' => 0,
                    'conflicts' => 0,
                    'contract_status' => 'expand_ready',
                ],
                'backfill' => [
                    'batch_count' => count($backfillBatches),
                    'resumed' => true,
                    'idempotent' => true,
                    'cumulative_gaps' => 0,
                    'cumulative_conflicts' => 0,
                    'cumulative_legacy_unresolved' => 0,
                    'source_snapshots_identical' => true,
                    'no_duplicate_execution_processing' => true,
                    'canonical_lineage_count' => $expectedExecutions,
                    'retention_classified_count' => $expectedConversations,
                ],
                'validate' => [
                    'batch_count' => count($validateBatches),
                    'resumed' => true,
                    'validated' => true,
                    'read_only' => true,
                    'cumulative_gaps' => 0,
                    'cumulative_conflicts' => 0,
                    'cumulative_legacy_unresolved' => 0,
                ],
                'shadow' => [
                    'read_only' => true,
                    'selection_matches_legacy_and_canonical' => true,
                ],
                'canary' => [
                    'rollout_percentage' => 25,
                    'canary_selects_candidate' => true,
                    'control_selects_root' => true,
                    'sticky_paths_idempotent' => true,
                    'open_candidate_activation_count' => 1,
                ],
                'rollback' => [
                    'new_conversations_select_root' => true,
                    'control_pin_resolves_root' => true,
                    'candidate_pin_fails_closed' => true,
                    'rollback_rollout_percentage' => 100,
                ],
                'contract' => [
                    'validated' => false,
                    'contract_status' => 'deferred_until_consumers_proven',
                    'legacy_preserved' => true,
                ],
                'safety' => [
                    'provider_calls' => count($provider->requests),
                    'handoffs' => $safety['handoffs'],
                    'usage_rows' => $safety['usage_rows'],
                    'billing_rows' => $safety['billing_rows'],
                    'payment_rows' => $safety['payment_rows'],
                    'credit_rows' => $safety['credit_rows'],
                    'foreign_tenant_mutations' => 0,
                ],
                'external' => [
                    'clean_exact_artifact',
                    'independent_security_review',
                    'live_ai_and_channel_providers',
                    'mysql_portability',
                    'postgresql_restricted_role_rls',
                    'production_operations',
                    'staging_runtime',
                ],
            ];
            $this->assertMachineFacts($facts);
            $reportPath = (string) getenv('DOVRA_PROGRAM05_COMPOSITE_REPORT');
            if ($reportPath === '') {
                $reportPath = sys_get_temp_dir().'\dovra-program05-composite-'.str_replace('-', '', (string) Str::uuid()).'.json';
            }
            Program05CompositeCertificateReport::writeExclusive($reportPath, $facts);
            self::assertFileExists($reportPath);
        } finally {
            foreach (glob($checkpointDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($checkpointDirectory);
        }
    }

    /** @return list<array<string,mixed>> */
    private function runBatches(string $phase, string $checkpoint): array
    {
        $batches = [];
        for ($guard = 0; $guard < 20; $guard++) {
            $exit = Artisan::call('dovra:ai-foundation-backfill', [
                '--limit' => 2,
                '--phase' => $phase,
                '--checkpoint' => $checkpoint,
                '--json' => true,
            ]);
            $output = Artisan::output();
            self::assertSame(0, $exit, $output);
            try {
                $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                self::fail('Program 05 command output was not JSON: '.$output);
            }
            self::assertIsArray($decoded);
            $batches[] = $decoded;
            self::assertFileExists($checkpoint);
            $checkpointPayload = json_decode((string) file_get_contents($checkpoint), true, 512, JSON_THROW_ON_ERROR);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) ($checkpointPayload['checkpoint_sha256'] ?? ''));
            if (! (bool) ($decoded['has_more'] ?? false)) {
                return $batches;
            }
        }
        self::fail('Program 05 checkpoint did not exhaust within the bounded batch guard.');
    }

    /** @param list<array<string,mixed>> $batches */
    private function assertResumedBatches(array $batches, int $expectedMinimum, int $expectedExecutions, int $expectedConversations, string $finalStatus): void
    {
        self::assertGreaterThanOrEqual($expectedMinimum, count($batches));
        $sequences = array_map(static fn (array $batch): int => (int) $batch['batch_sequence'], $batches);
        self::assertSame(range(1, count($batches)), $sequences);
        self::assertSame($finalStatus, (string) $batches[array_key_last($batches)]['checkpoint_status']);
        foreach ($batches as $index => $batch) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $batch['batch_checksum']);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $batch['validation_chain']);
            self::assertSame($index === array_key_last($batches) ? $finalStatus : 'partial', (string) $batch['checkpoint_status']);
        }
        $snapshots = array_map(static fn (array $batch): string => json_encode($batch['source_snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $batches);
        self::assertCount(1, array_values(array_unique($snapshots)));
        self::assertSame(0, (int) $batches[array_key_last($batches)]['cumulative_gaps']);
        self::assertSame(0, (int) $batches[array_key_last($batches)]['cumulative_conflicts']);
        self::assertSame(0, (int) $batches[array_key_last($batches)]['cumulative_legacy_unresolved']);
        self::assertSame($expectedExecutions, array_sum(array_map(static fn (array $batch): int => (int) $batch['registry_linked'], $batches)));
        self::assertSame($expectedConversations, array_sum(array_map(static fn (array $batch): int => (int) $batch['retention_classified'], $batches)));
        self::assertSame($expectedExecutions, array_sum(array_map(static fn (array $batch): int => (int) $batch['conversation_linked'], $batches)));
    }

    /** @return array{counts:array<string,int>,hash:string} */
    private function exactSnapshot(string $tenantId): array
    {
        $tables = [
            'ai_agent_definitions', 'ai_agent_versions', 'ai_prompt_definitions', 'ai_prompt_versions',
            'ai_release_policies', 'ai_release_decisions', 'ai_agent_activations', 'ai_conversations',
            'ai_conversation_participants', 'ai_messages', 'ai_executions', 'ai_handoffs', 'ai_usage_logs',
            'outbox_messages', 'payments', 'subscription_payments', 'ai_credit_reservations',
            'ai_credit_ledger_entries', 'global_billing_credit_reservations', 'global_billing_credit_ledger_entries',
        ];
        $rows = [];
        $counts = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }
            $tableRows = $query->get()->map(static fn (object $row): array => (array) $row)->all();
            usort($tableRows, static fn (array $left, array $right): int => strcmp(json_encode($left), json_encode($right)));
            $rows[$table] = $tableRows;
            $counts[$table] = count($tableRows);
        }
        ksort($rows);
        ksort($counts);

        return [
            'counts' => $counts,
            'hash' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ];
    }

    /** @return array<string,int> */
    private function tenantCounts(string $tenantId): array
    {
        $snapshot = $this->exactSnapshot($tenantId);

        return $snapshot['counts'];
    }

    /** @return array<string,int> */
    private function sideEffectCounts(): array
    {
        $groups = [
            'handoffs' => ['ai_handoffs'],
            'channel_delivery_rows' => ['channel_delivery_attempts', 'channel_delivery_outbox'],
            'usage_rows' => ['ai_usage_logs'],
            'billing_rows' => [
                'global_billing_market_providers',
                'global_billing_entitlements',
                'global_billing_credit_accounts',
                'global_billing_credit_reservations',
                'global_billing_credit_ledger_entries',
                'global_billing_credit_alerts',
                'global_billing_credit_reconciliations',
                'global_billing_lifecycle_events',
                'tenant_billing_references',
            ],
            'payment_rows' => ['payments', 'subscription_payments'],
            'credit_rows' => ['ai_credit_reservations', 'ai_credit_ledger_entries', 'ai_credit_grants', 'ai_credit_usage_alerts'],
        ];
        $counts = [];
        foreach ($groups as $key => $tables) {
            $counts[$key] = array_sum(array_map(fn (string $table): int => $this->requiredTableCount($table), $tables));
        }

        return $counts;
    }

    private function tableCount(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function requiredTableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            self::fail('Program 05 side-effect contract table is missing: '.$table);
        }

        return (int) DB::table($table)->count();
    }

    /** @return array{canonical:array<string,string|int|null>,legacy:array<string,string|int|null>} */
    private function foreignState(string $tenantId): array
    {
        return [
            'canonical' => Program05CompositeCertificateFixture::canonicalState($tenantId),
            'legacy' => Program05CompositeCertificateFixture::legacyState($tenantId),
        ];
    }

    /** @param array{canonical:array<string,string|int|null>,legacy:array<string,string|int|null>} $baseline */
    private function assertForeignState(string $tenantId, array $baseline): void
    {
        self::assertSame($baseline, $this->foreignState($tenantId));
    }

    private function assertNoEligibleForeignRoute(string $tenantId): void
    {
        $query = $this->registryQuery($tenantId, 'foreign-sentinel-actor', 'program05-foreign-subject');
        try {
            app(AiRegistryResolver::class)->resolve($query);
        } catch (AiExecutionDeniedException $exception) {
            self::assertSame('registry_selection', $exception->reason);

            return;
        }
        self::fail('Foreign sentinel unexpectedly resolved an eligible registry route.');
    }

    private function assertGuardRejectsMismatchedEffectiveDatabase(string $database): void
    {
        $originalEnvironmentDatabase = getenv('DB_DATABASE');
        $originalCompositeDatabase = getenv('DOVRA_PROGRAM05_COMPOSITE_DATABASE');
        $originalConfiguredDatabase = config('database.connections.sqlite.database');
        $staleDatabase = $database.'.stale';

        $mutations = [
            static function () use ($staleDatabase): void {
                putenv('DB_DATABASE='.$staleDatabase);
            },
            static function () use ($staleDatabase): void {
                config()->set('database.connections.sqlite.database', $staleDatabase);
            },
            static function () use ($staleDatabase): void {
                putenv('DOVRA_PROGRAM05_COMPOSITE_DATABASE='.$staleDatabase);
            },
        ];

        foreach ($mutations as $mutate) {
            $mutate();
            try {
                Program05CompositeCertificateFixture::create();
                self::fail('Program 05 fixture guard accepted a mismatched effective database path.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('exact disposable SQLite database path', $exception->getMessage());
            } finally {
                if ($originalEnvironmentDatabase === false) {
                    putenv('DB_DATABASE');
                } else {
                    putenv('DB_DATABASE='.$originalEnvironmentDatabase);
                }
                if ($originalCompositeDatabase === false) {
                    putenv('DOVRA_PROGRAM05_COMPOSITE_DATABASE');
                } else {
                    putenv('DOVRA_PROGRAM05_COMPOSITE_DATABASE='.$originalCompositeDatabase);
                }
                config()->set('database.connections.sqlite.database', $originalConfiguredDatabase);
            }
        }
    }

    private function registryQuery(string $tenantId, string $actorId, string $subject): AiRegistryQuery
    {
        return new AiRegistryQuery(
            tenantId: $tenantId,
            audience: 'private',
            capability: 'private_tenant_agent',
            channel: 'web',
            marketCode: 'HN',
            residencyClass: 'HN',
            actorId: $actorId,
            cohortSubject: $subject,
        );
    }

    /** @param array<string,mixed> $facts */
    private function assertMachineFacts(array $facts): void
    {
        self::assertSame(1, $facts['schema']);
        self::assertSame('LOCALLY_VERIFIED', $facts['status']);
        self::assertSame('NO_CONTRACT', $facts['decision']);
        self::assertSame('disposable_sqlite', $facts['scope']);
        self::assertSame(['expand', 'backfill', 'validate', 'shadow', 'canary', 'rollback', 'contract'], $facts['sequence']);
        self::assertTrue($facts['expand']['validated']);
        self::assertSame('expand_ready', $facts['expand']['contract_status']);
        self::assertGreaterThanOrEqual(3, $facts['backfill']['batch_count']);
        self::assertTrue($facts['backfill']['resumed']);
        self::assertTrue($facts['backfill']['idempotent']);
        self::assertSame(0, $facts['backfill']['cumulative_gaps']);
        self::assertSame(0, $facts['backfill']['cumulative_conflicts']);
        self::assertSame(0, $facts['backfill']['cumulative_legacy_unresolved']);
        self::assertGreaterThanOrEqual(3, $facts['validate']['batch_count']);
        self::assertTrue($facts['validate']['validated']);
        self::assertTrue($facts['validate']['read_only']);
        self::assertTrue($facts['shadow']['read_only']);
        self::assertTrue($facts['shadow']['selection_matches_legacy_and_canonical']);
        self::assertSame(25, $facts['canary']['rollout_percentage']);
        self::assertTrue($facts['canary']['canary_selects_candidate']);
        self::assertTrue($facts['canary']['control_selects_root']);
        self::assertTrue($facts['canary']['sticky_paths_idempotent']);
        self::assertTrue($facts['rollback']['new_conversations_select_root']);
        self::assertTrue($facts['rollback']['control_pin_resolves_root']);
        self::assertTrue($facts['rollback']['candidate_pin_fails_closed']);
        self::assertFalse($facts['contract']['validated']);
        self::assertSame('deferred_until_consumers_proven', $facts['contract']['contract_status']);
        self::assertTrue($facts['contract']['legacy_preserved']);
        foreach (['provider_calls', 'handoffs', 'usage_rows', 'billing_rows', 'payment_rows', 'credit_rows', 'foreign_tenant_mutations'] as $key) {
            self::assertSame(0, $facts['safety'][$key]);
        }
        self::assertSame([
            'clean_exact_artifact',
            'independent_security_review',
            'live_ai_and_channel_providers',
            'mysql_portability',
            'postgresql_restricted_role_rls',
            'production_operations',
            'staging_runtime',
        ], $facts['external']);
    }
}
