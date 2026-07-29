<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
