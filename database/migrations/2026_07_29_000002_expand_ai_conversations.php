<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('visibility', 16)->default('private');
            $table->string('origin_type', 32);
            $table->char('external_thread_hash', 64)->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->uuid('agent_version_id')->nullable();
            $table->string('locale', 16)->nullable();
            $table->char('market_country_code', 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('status', 24)->default('active');
            $table->string('retention_category', 32);
            $table->timestamp('retention_expires_at')->nullable();
            $table->unsignedBigInteger('last_message_sequence')->default(0);
            $table->timestamps();

            $table->foreign('agent_version_id', 'ai_conversations_agent_version_fk')
                ->references('id')->on('ai_agent_versions')->nullOnDelete();
            $table->index(['tenant_id', 'status', 'updated_at'], 'ai_conversations_tenant_status_idx');
            $table->unique(['tenant_id', 'external_thread_hash'], 'ai_conversations_tenant_thread_unique');
        });

        Schema::create('ai_conversation_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('conversation_id');
            $table->string('participant_type', 32);
            $table->string('participant_id', 64);
            $table->string('role', 32)->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id', 'ai_conversation_participants_conversation_fk')
                ->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->unique(
                ['tenant_id', 'conversation_id', 'participant_type', 'participant_id'],
                'ai_conversation_participants_scope_unique',
            );
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('conversation_id');
            $table->unsignedBigInteger('sequence');
            $table->string('direction', 16);
            $table->string('role', 32);
            $table->longText('content_ciphertext')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->string('encryption_key_version', 32)->nullable();
            $table->string('content_type', 64);
            $table->string('idempotency_key', 160);
            $table->char('idempotency_hash', 64);
            $table->string('status', 24)->default('accepted');
            $table->uuid('agent_version_id')->nullable();
            $table->uuid('prompt_version_id')->nullable();
            $table->uuid('provider_version_id')->nullable();
            $table->uuid('model_version_id')->nullable();
            $table->uuid('execution_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id', 'ai_messages_conversation_fk')
                ->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->foreign('agent_version_id', 'ai_messages_agent_version_fk')
                ->references('id')->on('ai_agent_versions')->nullOnDelete();
            $table->foreign('prompt_version_id', 'ai_messages_prompt_version_fk')
                ->references('id')->on('ai_prompt_versions')->nullOnDelete();
            $table->foreign('provider_version_id', 'ai_messages_provider_version_fk')
                ->references('id')->on('ai_provider_versions')->nullOnDelete();
            $table->foreign('model_version_id', 'ai_messages_model_version_fk')
                ->references('id')->on('ai_model_versions')->nullOnDelete();
            $table->foreign('execution_id', 'ai_messages_execution_fk')
                ->references('correlation_id')->on('ai_executions')->nullOnDelete();
            $table->unique(
                ['tenant_id', 'conversation_id', 'idempotency_key'],
                'ai_messages_tenant_conversation_idem_unique',
            );
            $table->unique(['conversation_id', 'sequence'], 'ai_messages_conversation_sequence_unique');
            $table->index(['tenant_id', 'conversation_id', 'created_at'], 'ai_messages_tenant_conversation_idx');
        });

        Schema::create('ai_message_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('message_id');
            $table->string('evidence_type', 32);
            $table->string('resource_type', 64)->nullable();
            $table->string('resource_id', 64)->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('message_id', 'ai_message_evidence_message_fk')
                ->references('id')->on('ai_messages')->cascadeOnDelete();
            $table->index(['tenant_id', 'message_id'], 'ai_message_evidence_tenant_message_idx');
        });

        Schema::create('ai_message_failures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('message_id');
            $table->string('failure_code', 64);
            $table->text('failure_detail')->nullable();
            $table->boolean('retryable')->default(false);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('message_id', 'ai_message_failures_message_fk')
                ->references('id')->on('ai_messages')->cascadeOnDelete();
            $table->index(['tenant_id', 'message_id', 'occurred_at'], 'ai_message_failures_message_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_message_failures');
        Schema::dropIfExists('ai_message_evidence');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversation_participants');
        Schema::dropIfExists('ai_conversations');
    }
};
