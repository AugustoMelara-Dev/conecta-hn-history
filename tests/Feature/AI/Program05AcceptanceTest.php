<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use Tests\TestCase;

/**
 * Program 05's bounded certification contract.
 *
 * Runtime behavior is covered by the focused AI suites. This test deliberately
 * checks the authority seams and migration vocabulary without running a
 * destructive migration as the PostgreSQL restricted role.
 */
final class Program05AcceptanceTest extends TestCase
{
    /**
     * The tables in the Program 05 RLS inventory that are tenant-scoped.
     * Platform provider/model rows are intentionally excluded.
     *
     * @var list<string>
     */
    private const PROGRAM_FIVE_TENANT_TABLES = [
        'ai_agent_definitions',
        'ai_agent_versions',
        'ai_prompt_definitions',
        'ai_prompt_versions',
        'ai_release_policies',
        'ai_agent_activations',
        'ai_conversations',
        'ai_conversation_participants',
        'ai_messages',
        'ai_message_evidence',
        'ai_message_failures',
        'ai_handoffs',
        'ai_evaluation_suites',
        'ai_evaluation_versions',
        'ai_evaluation_cases',
        'ai_evaluation_runs',
        'ai_evaluation_results',
        'ai_release_decisions',
        'ai_knowledge_chunks',
        'ai_tool_confirmations',
    ];

    public function test_canonical_web_chat_adapters_delegate_to_one_conversation_authority(): void
    {
        $controllerPaths = [
            'app/Http/Controllers/Api/V1/DirectoryGuideChatController.php',
            'app/Http/Controllers/Api/V1/TenantAgentChatController.php',
        ];

        foreach ($controllerPaths as $path) {
            $source = $this->source($path);

            self::assertStringContainsString('ConversationRuntime', $source, $path);
            self::assertStringContainsString('idempotencyKey', $source, $path);
            self::assertStringNotContainsString('AiGateway', $source, $path);
            self::assertStringNotContainsString('AiProvider', $source, $path);
            self::assertStringNotContainsString('PublicGroundedDiscovery $', $source, $path);
            self::assertStringNotContainsString('PrivateGroundedAgent $', $source, $path);
            self::assertStringNotContainsString('systemPrompt', $source, $path);
        }

        $runtime = $this->source('app/Application/Ai/Conversation/ConversationRuntime.php');
        foreach (['AiRegistryResolver', 'ConversationStore', 'TenantMarketResolver', 'AiHandoffService'] as $authority) {
            self::assertStringContainsString($authority, $runtime, $authority.' must remain in the runtime pipeline.');
        }
    }

    public function test_provider_model_agent_and_prompt_authority_is_versioned_and_secret_reference_only(): void
    {
        $source = $this->sources([
            'database/migrations/2026_07_29_000001_expand_ai_registry.php',
            'database/migrations/2026_07_29_000006_expand_ai_registry_lifecycle.php',
            'app/Application/Ai/Registry/AiRegistryResolver.php',
            'app/Application/Ai/Registry/AiRegistryLifecycle.php',
            'app/Application/Ai/Registry/AiRegistrySecretPolicy.php',
        ]);

        foreach ([
            'secret_reference',
            'provider_version_id',
            'model_version_id',
            'lifecycle_status',
            'kill_switch_enabled',
            'checksum',
            'rollout_percentage',
        ] as $contract) {
            self::assertStringContainsString($contract, $source, $contract.' is not represented in the canonical registry path.');
        }

        self::assertStringNotContainsString('OPENAI_API_KEY', $source);
        self::assertStringContainsString('sk-[A-Za-z0-9_-]{8,}', $source);
        self::assertFileExists(base_path('tests/Architecture/AiProviderAuthorityRatchetTest.php'));
        self::assertFileExists(base_path('tests/Architecture/AiRegistryImmutabilityRatchetTest.php'));
    }

    public function test_messages_prompts_handoffs_and_evidence_use_encryption_and_exact_lineage(): void
    {
        $source = $this->sources([
            'database/migrations/2026_07_29_000002_expand_ai_conversations.php',
            'database/migrations/2026_07_29_000007_expand_ai_message_evidence_lineage.php',
            'app/Infrastructure/AI/Conversation/EloquentConversationStore.php',
            'app/Infrastructure/AI/Security/LaravelAiContentCipher.php',
            'app/Application/Ai/Handoff/AiHandoffService.php',
        ]);

        foreach ([
            'content_ciphertext',
            'content_hash',
            'encryption_key_version',
            'execution_id',
            'agent_version_id',
            'prompt_version_id',
            'provider_version_id',
            'model_version_id',
            'source_tenant_id',
            'source_version',
            'excerpt_ciphertext',
            'context_ciphertext',
        ] as $contract) {
            self::assertStringContainsString($contract, $source, $contract.' is missing from the encrypted lineage contract.');
        }

        self::assertFileExists(base_path('tests/Feature/AI/ConversationEncryptionTest.php'));
        self::assertFileExists(base_path('tests/Feature/AI/GroundingLineageTest.php'));
    }

    public function test_duplicate_requests_and_provider_failures_have_durable_safe_outcomes(): void
    {
        $source = $this->sources([
            'database/migrations/2026_07_29_000002_expand_ai_conversations.php',
            'app/Infrastructure/AI/Conversation/EloquentConversationStore.php',
            'app/Application/Ai/Conversation/ConversationRuntime.php',
            'app/Application/Ai/Conversation/ConversationFailureMapper.php',
            'app/Application/Ai/Conversation/ConversationResponse.php',
            'app/Infrastructure/AI/ResilientAiGateway.php',
        ]);

        foreach ([
            'idempotency_key',
            'idempotency_hash',
            'ai_messages_tenant_conversation_idem_unique',
            'AiConversationConflictException',
            'replayed',
            'failAssistantMessage',
            'ai_request_conflict',
            'provider_unavailable',
            'refund',
        ] as $contract) {
            self::assertStringContainsString($contract, $source, $contract.' is not represented in the safe retry/failure path.');
        }

        self::assertFileExists(base_path('tests/Feature/AI/ConversationStoreTest.php'));
        self::assertFileExists(base_path('tests/Feature/AI/ResilientAiGatewayTest.php'));
        self::assertFileExists(base_path('tests/Feature/AI/AiExecutionReconciliationTest.php'));
    }

    public function test_handoff_and_evaluation_release_are_version_linked_and_blocking(): void
    {
        $source = $this->sources([
            'database/migrations/2026_07_29_000003_expand_ai_evaluations_and_handoffs.php',
            'database/migrations/2026_07_29_000008_expand_ai_handoff_lifecycle.php',
            'database/migrations/2026_07_29_000009_expand_ai_evaluation_evidence.php',
            'app/Application/Ai/Handoff/AiHandoffService.php',
            'app/Application/Ai/Evaluation/AiReleaseGate.php',
            'app/Application/Ai/Evaluation/AiEvaluationRunner.php',
        ]);

        foreach ([
            'active_deduplication_key',
            'context_ciphertext',
            'resolution_ciphertext',
            'return_to_agent',
            'blocking_failures',
            'result_checksum',
            'artifact_sha',
            'run_result_checksum',
            'evaluation_gate',
        ] as $contract) {
            self::assertStringContainsString($contract, $source, $contract.' is not represented in handoff/evaluation authority.');
        }

        self::assertFileExists(base_path('tests/Feature/AI/AiHandoffTest.php'));
        self::assertFileExists(base_path('tests/Feature/AI/AiEvaluationPlatformTest.php'));
        self::assertFileExists(base_path('tests/Feature/AI/GoldenDatasetEvaluationTest.php'));
    }

    public function test_private_ai_receives_explicit_market_context_and_grounding_uses_authorized_evidence(): void
    {
        $privateSource = $this->sources([
            'app/Application/Ai/Conversation/PrivateConversationInput.php',
            'app/Application/Ai/PrivateGroundedAgent.php',
            'app/Application/Ai/Grounding/GroundingValidator.php',
            'app/Application/Ai/Conversation/ConversationRuntime.php',
        ]);
        $marketSource = $this->source('app/Application/Markets/TenantMarketResolver.php');

        foreach (['locale', 'marketCountryCode', 'currency', 'timezone', 'TenantMarketResolver', 'evidence'] as $contract) {
            self::assertStringContainsString($contract, $privateSource, $contract.' is missing from the private market/grounding path.');
        }
        foreach (['countryCode', 'localeCode', 'currencyCode', 'timezone_name'] as $contract) {
            self::assertStringContainsString($contract, $marketSource, $contract.' is missing from market resolution.');
        }

        self::assertStringNotContainsString("'HNL'", $privateSource);
        self::assertStringNotContainsString('America/Tegucigalpa', $privateSource);
        self::assertFileExists(base_path('tests/Feature/AI/GlobalPrivateAgentTest.php'));
        self::assertFileExists(base_path('tests/Feature/AI/PublicGroundedDiscoveryTest.php'));
        self::assertFileExists(base_path('tests/Feature/AI/GroundingLineageTest.php'));
    }

    public function test_program_five_tenant_inventory_is_expand_only_and_forced_rls_is_runtime_proven(): void
    {
        $inventory = $this->source('app/Infrastructure/Tenancy/PostgresRlsPolicyInventory.php');
        $migration = $this->source('database/migrations/2026_07_29_000005_expand_postgres_rls_for_ai_foundation.php');
        $runtime = $this->source('tests/Feature/Tenancy/PostgresRlsRuntimeTest.php');

        foreach (self::PROGRAM_FIVE_TENANT_TABLES as $table) {
            self::assertStringContainsString("'{$table}'", $inventory, $table.' is absent from the canonical RLS inventory.');
        }
        foreach (['ENABLE ROW LEVEL SECURITY', 'FORCE ROW LEVEL SECURITY', "current_setting('app.tenant_id', true)"] as $contract) {
            self::assertStringContainsString($contract, $migration, $contract.' is absent from the Program 05 RLS migration.');
        }
        foreach (['current_user', 'rolbypassrls', 'runtime_is_owner', 'Cross-tenant', 'pgsql_platform'] as $contract) {
            self::assertStringContainsString($contract, $runtime, $contract.' is absent from the restricted-role proof.');
        }

        self::assertStringContainsString('expand', basename('2026_07_29_000005_expand_postgres_rls_for_ai_foundation.php'));
        self::assertFileExists(base_path('tests/Unit/Tenancy/PostgresRlsPolicyInventoryTest.php'));
    }

    public function test_backfill_and_compatibility_paths_are_present_before_any_contract(): void
    {
        $backfill = $this->sources([
            'app/Application/Ai/Conversation/AiFoundationBackfill.php',
            'app/Console/Commands/BackfillAiFoundationCommand.php',
            'tests/Feature/AI/AiFoundationBackfillTest.php',
            'app/Application/Ai/Conversation/ConversationResponse.php',
        ]);
        $plan = $this->source('docs/superpowers/plans/2026-07-29-dovra-program-05-ai-foundation.md');

        foreach (['legacyUnresolved', 'dovra:ai-foundation-backfill', 'limit', 'toArray', 'compatible'] as $contract) {
            self::assertStringContainsString($contract, $backfill, $contract.' is missing from the compatibility/backfill path.');
        }
        foreach (['backfill', 'validate', 'contract', 'rollback', 'compatibility'] as $phase) {
            self::assertStringContainsString($phase, $plan, $phase.' is missing from the expand/backfill/validate/contract plan.');
        }

        self::assertFileExists(base_path('tests/Architecture/AiConversationAuthorityRatchetTest.php'));
        self::assertFileExists(base_path('tests/Architecture/AiProviderAuthorityRatchetTest.php'));
    }

    /** @param list<string> $paths */
    private function sources(array $paths): string
    {
        return implode("\n\n", array_map(fn (string $path): string => $this->source($path), $paths));
    }

    private function source(string $path): string
    {
        $absolute = base_path($path);
        self::assertFileExists($absolute);
        $contents = file_get_contents($absolute);
        if (! is_string($contents)) {
            self::fail('Unable to read required certification source: '.$path);
        }

        return $contents;
    }
}
