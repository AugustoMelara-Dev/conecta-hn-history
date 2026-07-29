<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_executions', function (Blueprint $table): void {
            $table->uuid('conversation_id')->nullable();
            $table->uuid('request_message_id')->nullable();
            $table->uuid('response_message_id')->nullable();
            $table->uuid('provider_version_id')->nullable();
            $table->uuid('model_version_id')->nullable();
            $table->uuid('agent_version_id')->nullable();
            $table->uuid('prompt_version_id')->nullable();

            $table->foreign(['tenant_id', 'conversation_id'], 'ai_executions_conversation_fk')
                ->references(['tenant_id', 'id'])->on('ai_conversations')->restrictOnDelete();
            $table->foreign(['tenant_id', 'request_message_id'], 'ai_executions_request_message_fk')
                ->references(['tenant_id', 'id'])->on('ai_messages')->restrictOnDelete();
            $table->foreign(['tenant_id', 'response_message_id'], 'ai_executions_response_message_fk')
                ->references(['tenant_id', 'id'])->on('ai_messages')->restrictOnDelete();
            $table->foreign('provider_version_id', 'ai_executions_provider_version_fk')
                ->references('id')->on('ai_provider_versions')->nullOnDelete();
            $table->foreign('model_version_id', 'ai_executions_model_version_fk')
                ->references('id')->on('ai_model_versions')->nullOnDelete();
            $table->foreign(['tenant_id', 'agent_version_id'], 'ai_executions_agent_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'prompt_version_id'], 'ai_executions_prompt_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_prompt_versions')->restrictOnDelete();

            $table->index(['tenant_id', 'conversation_id'], 'ai_executions_tenant_conversation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_executions', function (Blueprint $table): void {
            $table->dropIndex('ai_executions_tenant_conversation_idx');
            $table->dropForeign('ai_executions_prompt_version_fk');
            $table->dropForeign('ai_executions_agent_version_fk');
            $table->dropForeign('ai_executions_model_version_fk');
            $table->dropForeign('ai_executions_provider_version_fk');
            $table->dropForeign('ai_executions_response_message_fk');
            $table->dropForeign('ai_executions_request_message_fk');
            $table->dropForeign('ai_executions_conversation_fk');
            $table->dropColumn([
                'conversation_id',
                'request_message_id',
                'response_message_id',
                'provider_version_id',
                'model_version_id',
                'agent_version_id',
                'prompt_version_id',
            ]);
        });
    }
};
