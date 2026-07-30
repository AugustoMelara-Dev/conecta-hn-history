<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            ['ai_evaluation_results', 'ai_evaluation_runs', 'evaluation_run_id'],
            ['ai_evaluation_results', 'ai_evaluation_cases', 'evaluation_case_id'],
            ['ai_release_decisions', 'ai_release_policies', 'release_policy_id'],
            ['ai_release_decisions', 'ai_evaluation_runs', 'evaluation_run_id'],
            ['ai_release_decisions', 'ai_agent_versions', 'agent_version_id'],
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
    ): void
    {
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
