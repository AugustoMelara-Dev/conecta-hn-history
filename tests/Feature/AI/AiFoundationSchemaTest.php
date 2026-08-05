<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\Support\DatabaseSchemaInspector;
use Tests\TestCase;

final class AiFoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_program05_schema_is_expand_only_versioned_and_tenant_safe(): void
    {
        self::assertTrue(Schema::hasColumns('ai_conversations', [
            'id', 'tenant_id', 'visibility', 'origin_type', 'external_thread_hash',
            'actor_id', 'agent_version_id', 'locale', 'market_country_code',
            'currency', 'timezone', 'status', 'retention_category',
            'retention_expires_at', 'last_message_sequence',
        ]));
        self::assertTrue(Schema::hasColumns('ai_messages', [
            'id', 'tenant_id', 'conversation_id', 'sequence', 'direction', 'role',
            'content_ciphertext', 'content_hash', 'encryption_key_version',
            'content_type', 'idempotency_key', 'idempotency_hash', 'status',
            'agent_version_id', 'prompt_version_id', 'provider_version_id',
            'model_version_id', 'execution_id',
        ]));
        self::assertTrue(Schema::hasColumns('ai_agent_activations', [
            'id', 'tenant_id', 'scope_type', 'scope_key', 'agent_version_id',
            'rollout_percentage', 'cohort_salt', 'release_decision_id',
            'previous_activation_id', 'effective_from', 'effective_until',
        ]));
        self::assertTrue(Schema::hasTable('ai_executions'));
        self::assertTrue(Schema::hasTable('ai_knowledge_chunks'));
    }

    public function test_provider_registry_uses_secret_references_and_version_lifecycle_fields(): void
    {
        self::assertTrue(Schema::hasColumns('ai_providers', [
            'provider_key',
            'adapter_key',
            'default_secret_reference',
        ]));
        self::assertTrue(Schema::hasColumns('ai_provider_versions', [
            'secret_reference',
            'status',
        ]));
    }

    public function test_message_evidence_has_expand_only_grounding_lineage_and_cipher_columns(): void
    {
        self::assertTrue(Schema::hasColumns('ai_message_evidence', [
            'evidence_id', 'source_type', 'source_id', 'source_version',
            'source_tenant_id', 'visibility', 'citation_label', 'fresh_at',
            'retrieval_rank', 'retrieval_score', 'currency', 'price_minor',
            'availability', 'excerpt_ciphertext', 'excerpt_hash',
            'excerpt_encryption_key_version',
        ]));
        self::assertSame(
            ['tenant_id', 'message_id', 'evidence_id'],
            DatabaseSchemaInspector::indexColumns(
                'ai_message_evidence',
                'ai_message_evidence_message_evidence_unique',
            ),
        );
        self::assertSame(
            ['source_tenant_id', 'source_type', 'source_id', 'source_version'],
            DatabaseSchemaInspector::indexColumns(
                'ai_message_evidence',
                'ai_message_evidence_source_version_idx',
            ),
        );

        foreach (['resource_type', 'resource_id', 'content_hash', 'metadata'] as $legacyColumn) {
            self::assertTrue(Schema::hasColumn('ai_message_evidence', $legacyColumn));
        }
    }

    public function test_handoffs_have_expand_only_governed_lifecycle_columns_and_indexes(): void
    {
        self::assertTrue(Schema::hasColumns('ai_handoffs', [
            'legacy_status', 'trigger_type', 'priority', 'queue_key', 'active_deduplication_key',
            'assigned_to_user_id', 'assigned_by_user_id', 'accepted_by_user_id',
            'resolved_by_user_id', 'agent_version_id', 'version', 'queued_at',
            'assigned_at', 'resolved_at', 'cancelled_at', 'expired_at',
            'resolution_ciphertext', 'resolution_hash', 'resolution_key_version',
            'return_to_agent',
        ]));
        self::assertSame(
            ['tenant_id', 'active_deduplication_key'],
            DatabaseSchemaInspector::indexColumns(
                'ai_handoffs',
                'ai_handoffs_tenant_active_dedup_unique',
            ),
        );
        self::assertSame(
            ['tenant_id', 'status', 'queue_key', 'queued_at'],
            DatabaseSchemaInspector::indexColumns(
                'ai_handoffs',
                'ai_handoffs_tenant_queue_status_queued_idx',
            ),
        );
    }

    public function test_evaluations_have_immutable_run_result_and_release_evidence_bindings(): void
    {
        self::assertTrue(Schema::hasColumns('ai_evaluation_runs', [
            'release_policy_id', 'baseline_run_id', 'release_policy_checksum',
            'evaluation_version_checksum', 'candidate_checksum', 'prompt_checksum',
            'provider_checksum', 'model_checksum', 'dataset_checksum', 'evaluator_version',
            'evaluator_checksum', 'artifact_sha', 'request_checksum', 'result_checksum',
            'deterministic_seed', 'aggregate_metrics', 'blocking_codes', 'blocking_failures',
        ]));
        self::assertTrue(Schema::hasColumns('ai_evaluation_results', [
            'input_checksum', 'result_checksum', 'latency_ms', 'cost_usd', 'diagnostic_code',
        ]));
        self::assertTrue(Schema::hasColumns('ai_release_decisions', [
            'prompt_version_id', 'provider_version_id', 'model_version_id',
            'release_policy_checksum', 'evaluation_version_checksum', 'prompt_checksum',
            'provider_checksum', 'model_checksum', 'dataset_checksum', 'evaluator_version',
            'evaluator_checksum', 'artifact_sha', 'run_result_checksum',
        ]));
        self::assertSame(
            ['tenant_id', 'request_checksum'],
            DatabaseSchemaInspector::indexColumns('ai_evaluation_runs', 'aierun_tenant_request_unique'),
        );
        self::assertSame(
            ['tenant_id', 'evaluation_run_id'],
            DatabaseSchemaInspector::indexColumns('ai_release_decisions', 'aird_tenant_run_unique'),
        );

        foreach (['status', 'triggered_by', 'started_at', 'completed_at'] as $legacyColumn) {
            self::assertTrue(Schema::hasColumn('ai_evaluation_runs', $legacyColumn));
        }
        foreach (['output_ciphertext', 'output_hash', 'encryption_key_version'] as $encryptedColumn) {
            self::assertTrue(Schema::hasColumn('ai_evaluation_results', $encryptedColumn));
        }
    }

    public function test_registry_versions_have_explicit_lifecycle_and_release_metadata(): void
    {
        foreach ([
            'ai_provider_versions',
            'ai_model_versions',
            'ai_agent_versions',
            'ai_prompt_versions',
        ] as $table) {
            self::assertTrue(Schema::hasColumns($table, [
                'lifecycle_status',
                'kill_switch_enabled',
                'blocked_at',
                'blocked_by',
                'blocked_reason',
            ]), "Expected explicit lifecycle authority on [{$table}].");
        }

        self::assertTrue(Schema::hasColumns('ai_agent_activations', [
            'activated_by',
            'reason',
            'lock_version',
        ]));
        self::assertTrue(Schema::hasColumns('ai_release_decisions', [
            'candidate_checksum',
            'valid_from',
            'valid_until',
            'revoked_at',
            'revoked_by',
            'revocation_reason',
        ]));
    }

    public function test_activation_reason_generates_portable_mysql_varchar_sql(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_07_29_000006_expand_ai_registry_lifecycle.php',
        ));
        self::assertIsString($migration);
        self::assertStringContainsString(
            "\$table->string('reason', 500)->default('Program 05 lifecycle backfill')",
            $migration,
        );
        self::assertStringNotContainsString("\$table->text('reason')->default(", $migration);

        $connection = new MySqlConnection(new PDO('sqlite::memory:'));
        $connection->setSchemaGrammar(new MySqlGrammar($connection));
        $blueprint = new Blueprint(
            $connection,
            'ai_agent_activations',
            static function (Blueprint $table): void {
                $table->string('reason', 500)->default('Program 05 lifecycle backfill');
            },
        );
        $sql = implode(' ', $blueprint->toSql());

        self::assertStringContainsString(
            "varchar(500) not null default 'program 05 lifecycle backfill'",
            strtolower($sql),
        );
        self::assertStringNotContainsString(' text ', strtolower($sql));
    }

    public function test_tenant_owned_ai_relations_reference_tenant_scoped_parent_keys(): void
    {
        $relations = [
            ['ai_agent_versions', 'ai_agent_definitions', 'agent_definition_id'],
            ['ai_prompt_versions', 'ai_prompt_definitions', 'prompt_definition_id'],
            ['ai_agent_activations', 'ai_agent_versions', 'agent_version_id'],
            ['ai_agent_activations', 'ai_agent_activations', 'previous_activation_id'],
            ['ai_agent_activations', 'ai_release_decisions', 'release_decision_id'],
            ['ai_conversations', 'ai_agent_versions', 'agent_version_id'],
            ['ai_conversation_participants', 'ai_conversations', 'conversation_id'],
            ['ai_messages', 'ai_conversations', 'conversation_id'],
            ['ai_messages', 'ai_agent_versions', 'agent_version_id'],
            ['ai_messages', 'ai_prompt_versions', 'prompt_version_id'],
            ['ai_message_evidence', 'ai_messages', 'message_id'],
            ['ai_message_failures', 'ai_messages', 'message_id'],
            ['ai_handoffs', 'ai_conversations', 'conversation_id'],
            ['ai_handoffs', 'ai_messages', 'message_id'],
            ['ai_evaluation_versions', 'ai_evaluation_suites', 'evaluation_suite_id'],
            ['ai_evaluation_cases', 'ai_evaluation_versions', 'evaluation_version_id'],
            ['ai_evaluation_runs', 'ai_evaluation_versions', 'evaluation_version_id'],
            ['ai_evaluation_runs', 'ai_agent_versions', 'agent_version_id'],
            ['ai_evaluation_runs', 'ai_prompt_versions', 'prompt_version_id'],
            ['ai_evaluation_runs', 'ai_release_policies', 'release_policy_id'],
            ['ai_evaluation_runs', 'ai_evaluation_runs', 'baseline_run_id'],
            ['ai_evaluation_results', 'ai_evaluation_runs', 'evaluation_run_id'],
            ['ai_evaluation_results', 'ai_evaluation_cases', 'evaluation_case_id'],
            ['ai_release_decisions', 'ai_release_policies', 'release_policy_id'],
            ['ai_release_decisions', 'ai_evaluation_runs', 'evaluation_run_id'],
            ['ai_release_decisions', 'ai_agent_versions', 'agent_version_id'],
            ['ai_release_decisions', 'ai_prompt_versions', 'prompt_version_id'],
        ];

        foreach ($relations as [$table, $parent, $column]) {
            $this->assertTenantScopedForeignKey($table, $parent, $column);
        }

        $this->assertTenantScopedForeignKey('ai_messages', 'ai_executions', 'execution_id', 'correlation_id');
        $this->assertTenantScopedForeignKey('ai_executions', 'ai_conversations', 'conversation_id');
        $this->assertTenantScopedForeignKey('ai_executions', 'ai_messages', 'request_message_id');
        $this->assertTenantScopedForeignKey('ai_executions', 'ai_messages', 'response_message_id');
        $this->assertTenantScopedForeignKey('ai_executions', 'ai_agent_versions', 'agent_version_id');
        $this->assertTenantScopedForeignKey('ai_executions', 'ai_prompt_versions', 'prompt_version_id');
    }

    private function assertTenantScopedForeignKey(
        string $table,
        string $parent,
        string $column,
        string $parentColumn = 'id',
    ): void {
        /** @var list<object{id: int, table: string, from: string, to: string}> $foreignKeys */
        $foreignKeys = DB::select("PRAGMA foreign_key_list('{$table}')");
        $groups = [];

        foreach ($foreignKeys as $foreignKey) {
            $groups[$foreignKey->id]['table'] = $foreignKey->table;
            $groups[$foreignKey->id]['columns'][$foreignKey->from] = $foreignKey->to;
        }

        foreach ($groups as $group) {
            if ($group['table'] === $parent
                && ($group['columns']['tenant_id'] ?? null) === 'tenant_id'
                && ($group['columns'][$column] ?? null) === $parentColumn) {
                self::assertCount(2, $group['columns']);

                return;
            }
        }

        self::fail("Expected [{$table}.{$column}] to reference [{$parent}] within the same tenant.");
    }
}
