<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\Registry\AiRegistryBackfill;
use App\Application\Ai\Registry\AiRegistryLifecycle;
use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Ai\Registry\Commands\ActivateAgentVersionCommand;
use App\Application\Ai\Registry\Commands\PromoteAiVersionCommand;
use App\Application\Ai\Security\EncryptedAiContent;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Infrastructure\AI\Security\LaravelAiContentCipher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Fakes\AI\FakeAiProvider;
use Tests\TestCase;

final class AiRegistryBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_idempotent_versioned_provider_model_agent_and_prompt_registries(): void
    {
        $platformTenant = Tenant::factory()->create();
        $allowlistedTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), [(string) $allowlistedTenant->getKey()]);
        putenv('OPENAI_API_KEY=resolved-openai-api-key-value');

        $first = app(AiRegistryBackfill::class)->run();
        $second = app(AiRegistryBackfill::class)->run();

        self::assertSame($first->providerVersions, $second->providerVersions);
        self::assertSame($first->promptVersions, $second->promptVersions);
        self::assertDatabaseHas('ai_providers', [
            'provider_key' => 'openai',
            'adapter_key' => 'openai_structured_text',
            'default_secret_reference' => 'openai.primary',
        ]);
        self::assertDatabaseHas('ai_provider_versions', [
            'secret_reference' => 'openai.primary',
            'status' => 'active',
            'lifecycle_status' => 'draft',
        ]);
        self::assertDatabaseHas('ai_agent_definitions', [
            'key' => 'public.discovery',
            'visibility' => 'platform_public',
            'tenant_id' => (string) $platformTenant->getKey(),
        ]);
        self::assertDatabaseHas('ai_agent_definitions', [
            'key' => 'tenant.private_assistant',
            'visibility' => 'tenant_private',
            'tenant_id' => (string) $allowlistedTenant->getKey(),
        ]);
        self::assertDatabaseMissing('ai_provider_versions', [
            'secret_reference' => 'resolved-openai-api-key-value',
        ]);
        foreach ([
            'ai_provider_versions',
            'ai_model_versions',
            'ai_agent_versions',
            'ai_prompt_versions',
        ] as $versionTable) {
            self::assertSame(0, DB::table($versionTable)
                ->where(static function ($query): void {
                    $query->where('lifecycle_status', '!=', 'draft')
                        ->orWhere('kill_switch_enabled', true);
                })
                ->count());
        }
        self::assertSame(
            DB::table('ai_agent_activations')->count(),
            DB::table('ai_agent_activations')
                ->where('activated_by', 'registry_backfill')
                ->where('reason', 'Program 05 registry bootstrap')
                ->where('lock_version', 0)
                ->count(),
        );

        $stored = collect([
            'ai_providers', 'ai_provider_versions', 'ai_models', 'ai_model_versions',
            'ai_agent_definitions', 'ai_agent_versions', 'ai_prompt_definitions', 'ai_prompt_versions',
        ])->flatMap(static fn (string $table) => DB::table($table)->get())
            ->map(static fn (object $row): string => json_encode($row, JSON_THROW_ON_ERROR));
        self::assertFalse($stored->contains(static fn (string $json): bool => str_contains($json, 'resolved-openai-api-key-value')));

        $prompt = DB::table('ai_prompt_versions')
            ->join('ai_prompt_definitions', 'ai_prompt_definitions.id', '=', 'ai_prompt_versions.prompt_definition_id')
            ->where('ai_prompt_definitions.key', 'public.discovery.system')
            ->select('ai_prompt_versions.*')
            ->first();
        self::assertNotNull($prompt);
        self::assertStringNotContainsString('You are Dovra AI public discovery', $prompt->template_ciphertext);
        self::assertStringStartsWith('You are Dovra AI public discovery', app(LaravelAiContentCipher::class)->decrypt(
            (string) $platformTenant->getKey(),
            $prompt->id,
            'prompt_template',
            new EncryptedAiContent($prompt->template_ciphertext, $prompt->template_hash, $prompt->encryption_key_version),
        ));
    }

    public function test_policy_writer_locks_tenant_then_policy_in_the_shared_order(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        putenv('OPENAI_API_KEY=resolved-openai-api-key-value');
        $connection = DB::connection();
        $originalGrammar = $connection->getQueryGrammar();
        $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
        {
            protected function compileLock(Builder $query, $value)
            {
                return $value ? ' /* registry-for-update */' : '';
            }
        });
        $locked = [];
        $capture = true;
        DB::listen(static function (QueryExecuted $event) use (&$locked, &$capture): void {
            if ($capture && str_contains($event->sql, 'registry-for-update')) {
                $locked[] = $event->sql;
            }
        });

        try {
            app(AiRegistryBackfill::class)->run();
        } finally {
            $capture = false;
            $connection->setQueryGrammar($originalGrammar);
        }

        $tenantLock = collect($locked)->search(
            static fn (string $sql): bool => str_contains($sql, '"tenants"'),
        );
        $policyLock = collect($locked)->search(
            static fn (string $sql): bool => str_contains($sql, '"ai_release_policies"'),
        );
        self::assertIsInt($tenantLock);
        self::assertIsInt($policyLock);
        self::assertLessThan($policyLock, $tenantLock);
    }

    public function test_backfilled_versions_can_follow_the_governed_release_path_and_resolve(): void
    {
        $tenant = Tenant::factory()->create();
        $this->configureRegistry((string) $tenant->id, []);
        app(AiRegistryBackfill::class)->run();
        $admin = User::factory()->superAdmin()->create();
        config()->set('auth.super_admins', [$admin->email]);
        app()->instance(AiProviderPool::class, new ConfiguredAiProviderPool([
            'openai_structured_text' => [
                'openai.primary' => new FakeAiProvider,
            ],
        ]));

        $agent = DB::table('ai_agent_versions')
            ->join(
                'ai_agent_definitions',
                'ai_agent_definitions.id',
                '=',
                'ai_agent_versions.agent_definition_id',
            )
            ->where('ai_agent_definitions.key', 'public.discovery')
            ->select('ai_agent_versions.*')
            ->first();
        self::assertNotNull($agent);
        $configuration = json_decode(
            (string) $agent->configuration,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $promptVersionId = (string) $configuration['prompt_version_id'];
        $modelVersionId = (string) $configuration['model_version_ids'][0];
        $providerVersionId = (string) DB::table('ai_model_versions')
            ->where('id', $modelVersionId)
            ->value('provider_version_id');
        $lifecycle = app(AiRegistryLifecycle::class);
        foreach ([
            'provider' => $providerVersionId,
            'prompt' => $promptVersionId,
            'model' => $modelVersionId,
            'agent' => (string) $agent->id,
        ] as $versionType => $versionId) {
            $lifecycle->promote(new PromoteAiVersionCommand(
                actorId: (string) $admin->id,
                tenantId: (string) $tenant->id,
                versionType: $versionType,
                versionId: $versionId,
                targetStatus: 'candidate',
                releaseDecisionId: null,
                reason: "Freeze backfilled {$versionType} candidate",
            ));
        }
        $decisionId = $this->releaseBackfillDecision(
            (string) $tenant->id,
            (string) $admin->id,
            (string) $agent->id,
            $promptVersionId,
            $providerVersionId,
            $modelVersionId,
        );
        $lifecycle->promote(new PromoteAiVersionCommand(
            actorId: (string) $admin->id,
            tenantId: (string) $tenant->id,
            versionType: 'agent',
            versionId: (string) $agent->id,
            targetStatus: 'eligible',
            releaseDecisionId: $decisionId,
            reason: 'Promote the evaluated backfill set atomically',
        ));
        $lifecycle->activate(new ActivateAgentVersionCommand(
            actorId: (string) $admin->id,
            tenantId: (string) $tenant->id,
            scopeType: 'tenant',
            scopeKey: (string) $tenant->id,
            agentVersionId: (string) $agent->id,
            releaseDecisionId: $decisionId,
            rolloutPercentage: 100,
            reason: 'Activate the governed backfill release',
            expectedActivationId: null,
            expectedLockVersion: null,
        ));

        $selection = app(AiRegistryResolver::class)->resolve(new AiRegistryQuery(
            tenantId: (string) $tenant->id,
            audience: 'public',
            capability: 'public_discovery',
            channel: 'web',
            marketCountryCode: 'HN',
            actorId: null,
            cohortSubject: 'public-backfill-release',
        ));
        self::assertSame((string) $agent->id, $selection->agentVersionId);
        self::assertSame($providerVersionId, $selection->providerVersionId);
    }

    public function test_it_creates_an_immutable_model_version_when_configuration_changes(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);

        app(AiRegistryBackfill::class)->run();
        config()->set('ai.providers.openai.models.gpt-4o-mini.input_usd_per_million', 0.25);
        $report = app(AiRegistryBackfill::class)->run();

        self::assertSame(2, $report->modelVersions);
        self::assertSame(15, (int) DB::table('ai_model_versions')->orderBy('version')->value('input_cost_minor'));
        self::assertSame(25, (int) DB::table('ai_model_versions')->orderByDesc('version')->value('input_cost_minor'));
    }

    public function test_it_creates_a_provider_version_when_the_secret_reference_rotates(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);

        app(AiRegistryBackfill::class)->run();
        config()->set('ai.providers.openai.secret_reference', 'openai.rotated');
        $report = app(AiRegistryBackfill::class)->run();

        self::assertSame(2, $report->providerVersions);
        self::assertDatabaseHas('ai_provider_versions', ['secret_reference' => 'openai.primary', 'version' => 1]);
        self::assertDatabaseHas('ai_provider_versions', ['secret_reference' => 'openai.rotated', 'version' => 2]);
    }

    public function test_it_records_a_gap_when_any_capability_model_is_unknown(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        config()->set('ai.capabilities.public_discovery.models', ['gpt-4o-mini', 'unconfigured-fallback']);

        $report = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $report->gaps);
        self::assertDatabaseMissing('ai_agent_definitions', ['key' => 'public.discovery']);
    }

    public function test_it_versions_the_agent_when_a_secondary_capability_model_changes(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        config()->set('ai.providers.openai.models.gpt-4o', [
            'input_usd_per_million' => 2.50,
            'output_usd_per_million' => 10.00,
        ]);

        app(AiRegistryBackfill::class)->run();
        config()->set('ai.capabilities.public_discovery.models', ['gpt-4o-mini', 'gpt-4o']);
        $report = app(AiRegistryBackfill::class)->run();

        self::assertSame(2, $report->agentVersions);
        self::assertSame(['gpt-4o-mini', 'gpt-4o'], json_decode(
            DB::table('ai_agent_versions')->orderByDesc('version')->value('configuration'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        )['models']);
    }

    public function test_it_records_a_conflict_without_mutating_ambiguous_open_activations(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        app(AiRegistryBackfill::class)->run();

        $activation = DB::table('ai_agent_activations')->first();
        self::assertNotNull($activation);
        DB::table('ai_agent_activations')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $activation->tenant_id,
            'scope_type' => $activation->scope_type,
            'scope_key' => $activation->scope_key,
            'agent_version_id' => $activation->agent_version_id,
            'rollout_percentage' => $activation->rollout_percentage,
            'cohort_salt' => $activation->cohort_salt,
            'release_decision_id' => null,
            'previous_activation_id' => null,
            'effective_from' => now()->addSecond(),
            'effective_until' => null,
            'activated_by' => 'regression_fixture',
            'reason' => 'Ambiguous activation regression fixture',
            'lock_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $report->conflicts);
        self::assertSame(2, DB::table('ai_agent_activations')->whereNull('effective_until')->count());
    }

    public function test_it_creates_an_encrypted_prompt_version_when_the_key_version_changes(): void
    {
        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        config()->set('ai.execution_retention.encryption_key_version', 'v1');

        app(AiRegistryBackfill::class)->run();
        config()->set('ai.execution_retention.encryption_key_version', 'v2');
        $report = app(AiRegistryBackfill::class)->run();

        self::assertSame(2, $report->promptVersions);
        self::assertSame('v2', DB::table('ai_prompt_versions')->orderByDesc('version')->value('encryption_key_version'));
    }

    public function test_it_records_gaps_for_missing_or_invalid_public_owners_and_unknown_models(): void
    {
        $this->configureRegistry(null, []);
        $missingOwner = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $missingOwner->gaps);
        self::assertDatabaseMissing('ai_agent_definitions', ['key' => 'public.discovery']);

        $this->configureRegistry('not-a-uuid', []);
        config()->set('ai.capabilities.public_discovery.models', ['unconfigured-model']);
        $invalidOwner = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $invalidOwner->gaps);
        self::assertDatabaseMissing('ai_agent_definitions', ['key' => 'public.discovery']);

        $platformTenant = Tenant::factory()->create();
        $this->configureRegistry((string) $platformTenant->getKey(), []);
        config()->set('ai.capabilities.public_discovery.models', ['unconfigured-model']);
        $unknownModel = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $unknownModel->gaps);
        self::assertDatabaseMissing('ai_agent_definitions', ['key' => 'public.discovery']);
    }

    public function test_it_records_conflicts_without_mutating_an_ambiguous_provider_definition(): void
    {
        $this->configureRegistry(null, []);
        DB::table('ai_providers')->insert([
            'id' => '00000000-0000-4000-8000-000000000001',
            'provider_key' => 'openai',
            'adapter_key' => 'different_adapter',
            'default_secret_reference' => 'different.reference',
            'name' => 'Conflicting OpenAI',
            'status' => 'active',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(AiRegistryBackfill::class)->run();

        self::assertGreaterThan(0, $report->conflicts);
        self::assertSame('different_adapter', DB::table('ai_providers')->where('provider_key', 'openai')->value('adapter_key'));
    }

    public function test_prompt_content_is_encrypted_with_tenant_bound_associated_data(): void
    {
        $cipher = app(LaravelAiContentCipher::class);
        $content = $cipher->encrypt('00000000-0000-4000-8000-000000000010', '00000000-0000-4000-8000-000000000011', 'prompt_template', 'grounded prompt');

        self::assertNotSame('grounded prompt', $content->ciphertext);
        self::assertSame('grounded prompt', $cipher->decrypt(
            '00000000-0000-4000-8000-000000000010',
            '00000000-0000-4000-8000-000000000011',
            'prompt_template',
            $content,
        ));
        $this->expectException(\RuntimeException::class);
        $cipher->decrypt('00000000-0000-4000-8000-000000000012', '00000000-0000-4000-8000-000000000011', 'prompt_template', $content);
    }

    private function releaseBackfillDecision(
        string $tenantId,
        string $actorId,
        string $agentVersionId,
        string $promptVersionId,
        string $providerVersionId,
        string $modelVersionId,
    ): string {
        $suiteId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $caseId = (string) Str::uuid();
        $runId = (string) Str::uuid();
        DB::table('ai_evaluation_suites')->insert([
            'id' => $suiteId,
            'tenant_id' => $tenantId,
            'key' => 'backfill.release',
            'name' => 'Backfill release evaluation',
            'visibility' => 'public',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_versions')->insert([
            'id' => $versionId,
            'tenant_id' => $tenantId,
            'evaluation_suite_id' => $suiteId,
            'version' => 1,
            'checksum' => hash('sha256', $versionId),
            'configuration' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'released_at' => now()->subSeconds(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_cases')->insert([
            'id' => $caseId,
            'tenant_id' => $tenantId,
            'evaluation_version_id' => $versionId,
            'key' => 'backfill.release.case',
            'input_ciphertext' => 'fixture',
            'input_hash' => hash('sha256', 'fixture'),
            'encryption_key_version' => 'v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_runs')->insert([
            'id' => $runId,
            'tenant_id' => $tenantId,
            'evaluation_version_id' => $versionId,
            'agent_version_id' => $agentVersionId,
            'prompt_version_id' => $promptVersionId,
            'provider_version_id' => $providerVersionId,
            'model_version_id' => $modelVersionId,
            'status' => 'completed',
            'triggered_by' => $actorId,
            'started_at' => now()->subSeconds(2),
            'completed_at' => now()->subSecond(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_evaluation_results')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'evaluation_run_id' => $runId,
            'evaluation_case_id' => $caseId,
            'status' => 'passed',
            'score_minor' => 100,
            'completed_at' => now()->subSecond(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $policyId = (string) DB::table('ai_release_policies')
            ->where('tenant_id', $tenantId)
            ->value('id');
        $decisionId = (string) Str::uuid();
        DB::table('ai_release_decisions')->insert([
            'id' => $decisionId,
            'tenant_id' => $tenantId,
            'release_policy_id' => $policyId,
            'evaluation_run_id' => $runId,
            'agent_version_id' => $agentVersionId,
            'decision' => 'passed',
            'decided_by' => $actorId,
            'evidence_hash' => hash('sha256', $decisionId),
            'rationale' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'decided_at' => now(),
            'candidate_checksum' => (string) DB::table('ai_agent_versions')
                ->where('id', $agentVersionId)
                ->value('checksum'),
            'valid_from' => now()->subSecond(),
            'valid_until' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $decisionId;
    }

    /** @param list<string> $allowlist */
    private function configureRegistry(?string $platformTenantId, array $allowlist): void
    {
        config()->set('ai.public.billing_tenant_id', $platformTenantId);
        config()->set('ai.private.enabled', true);
        config()->set('ai.private.tenant_allowlist', $allowlist);
        config()->set('ai.providers', [
            'openai' => [
                'enabled' => true,
                'adapter_key' => 'openai_structured_text',
                'secret_reference' => 'openai.primary',
                'models' => [
                    'gpt-4o-mini' => [
                        'input_usd_per_million' => 0.15,
                        'output_usd_per_million' => 0.60,
                    ],
                ],
            ],
        ]);
        config()->set('ai.capabilities', [
            'public_discovery' => [
                'enabled' => true,
                'provider' => 'openai',
                'models' => ['gpt-4o-mini'],
                'tools' => ['public_catalog.search'],
            ],
            'private_tenant_agent' => [
                'enabled' => true,
                'provider' => 'openai',
                'models' => ['gpt-4o-mini'],
                'tools' => ['tenant_catalog.read', 'tenant_availability.read'],
            ],
        ]);
    }
}
