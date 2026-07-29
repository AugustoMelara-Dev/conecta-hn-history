<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_handoffs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('conversation_id');
            $table->uuid('message_id')->nullable();
            $table->string('from_type', 32);
            $table->string('to_type', 32);
            $table->string('status', 24)->default('pending');
            $table->longText('context_ciphertext')->nullable();
            $table->char('context_hash', 64)->nullable();
            $table->string('encryption_key_version', 32)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'conversation_id'], 'ai_handoffs_conversation_fk')
                ->references(['tenant_id', 'id'])->on('ai_conversations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'message_id'], 'ai_handoffs_message_fk')
                ->references(['tenant_id', 'id'])->on('ai_messages')->restrictOnDelete();
            $table->index(['tenant_id', 'conversation_id', 'status'], 'ai_handoffs_tenant_conversation_idx');
        });

        Schema::create('ai_evaluation_suites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 128);
            $table->string('name', 160);
            $table->string('visibility', 16)->default('private');
            $table->string('status', 24)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key'], 'ai_evaluation_suites_tenant_key_unique');
            $table->unique(['tenant_id', 'id'], 'ai_evaluation_suites_tenant_id_unique');
        });

        Schema::create('ai_evaluation_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('evaluation_suite_id');
            $table->unsignedInteger('version');
            $table->char('checksum', 64);
            $table->json('configuration');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'evaluation_suite_id'], 'ai_evaluation_versions_suite_fk')
                ->references(['tenant_id', 'id'])->on('ai_evaluation_suites')->cascadeOnDelete();
            $table->unique(['evaluation_suite_id', 'version'], 'ai_evaluation_versions_number_unique');
            $table->unique(['evaluation_suite_id', 'checksum'], 'ai_evaluation_versions_checksum_unique');
            $table->unique(['tenant_id', 'id'], 'ai_evaluation_versions_tenant_id_unique');
            $table->index(['tenant_id', 'evaluation_suite_id'], 'ai_evaluation_versions_tenant_suite_idx');
        });

        Schema::create('ai_evaluation_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('evaluation_version_id');
            $table->string('key', 128);
            $table->longText('input_ciphertext');
            $table->char('input_hash', 64);
            $table->longText('expected_ciphertext')->nullable();
            $table->char('expected_hash', 64)->nullable();
            $table->string('encryption_key_version', 32);
            $table->json('criteria')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'evaluation_version_id'], 'ai_evaluation_cases_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_evaluation_versions')->cascadeOnDelete();
            $table->unique(['evaluation_version_id', 'key'], 'ai_evaluation_cases_version_key_unique');
            $table->unique(['tenant_id', 'id'], 'ai_evaluation_cases_tenant_id_unique');
            $table->index(['tenant_id', 'evaluation_version_id'], 'ai_evaluation_cases_tenant_version_idx');
        });

        Schema::create('ai_evaluation_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('evaluation_version_id');
            $table->uuid('agent_version_id')->nullable();
            $table->uuid('prompt_version_id')->nullable();
            $table->uuid('provider_version_id')->nullable();
            $table->uuid('model_version_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('triggered_by', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'evaluation_version_id'], 'ai_evaluation_runs_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_evaluation_versions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'agent_version_id'], 'ai_evaluation_runs_agent_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'prompt_version_id'], 'ai_evaluation_runs_prompt_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_prompt_versions')->restrictOnDelete();
            $table->foreign('provider_version_id', 'ai_evaluation_runs_provider_version_fk')
                ->references('id')->on('ai_provider_versions')->nullOnDelete();
            $table->foreign('model_version_id', 'ai_evaluation_runs_model_version_fk')
                ->references('id')->on('ai_model_versions')->nullOnDelete();
            $table->unique(['tenant_id', 'id'], 'ai_evaluation_runs_tenant_id_unique');
            $table->index(['tenant_id', 'status', 'created_at'], 'ai_evaluation_runs_tenant_status_idx');
        });

        Schema::create('ai_evaluation_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('evaluation_run_id');
            $table->uuid('evaluation_case_id');
            $table->string('status', 24);
            $table->integer('score_minor')->nullable();
            $table->longText('output_ciphertext')->nullable();
            $table->char('output_hash', 64)->nullable();
            $table->string('encryption_key_version', 32)->nullable();
            $table->json('metrics')->nullable();
            $table->text('failure_detail')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'evaluation_run_id'], 'ai_evaluation_results_run_fk')
                ->references(['tenant_id', 'id'])->on('ai_evaluation_runs')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'evaluation_case_id'], 'ai_evaluation_results_case_fk')
                ->references(['tenant_id', 'id'])->on('ai_evaluation_cases')->restrictOnDelete();
            $table->unique(['evaluation_run_id', 'evaluation_case_id'], 'ai_evaluation_results_run_case_unique');
            $table->index(['tenant_id', 'evaluation_run_id'], 'ai_evaluation_results_tenant_run_idx');
        });

        Schema::create('ai_release_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('release_policy_id')->nullable();
            $table->uuid('evaluation_run_id')->nullable();
            $table->uuid('agent_version_id')->nullable();
            $table->string('decision', 24);
            $table->string('decided_by', 64)->nullable();
            $table->char('evidence_hash', 64)->nullable();
            $table->json('rationale')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->foreign(['tenant_id', 'release_policy_id'], 'ai_release_decisions_policy_fk')
                ->references(['tenant_id', 'id'])->on('ai_release_policies')->restrictOnDelete();
            $table->foreign(['tenant_id', 'evaluation_run_id'], 'ai_release_decisions_run_fk')
                ->references(['tenant_id', 'id'])->on('ai_evaluation_runs')->restrictOnDelete();
            $table->foreign(['tenant_id', 'agent_version_id'], 'ai_release_decisions_agent_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
            $table->unique(['tenant_id', 'id'], 'ai_release_decisions_tenant_id_unique');
            $table->index(['tenant_id', 'agent_version_id', 'decided_at'], 'ai_release_decisions_agent_idx');
        });

        Schema::table('ai_agent_activations', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'release_decision_id'], 'ai_agent_activations_release_decision_fk')
                ->references(['tenant_id', 'id'])->on('ai_release_decisions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_activations', function (Blueprint $table): void {
            $table->dropForeign('ai_agent_activations_release_decision_fk');
        });

        Schema::dropIfExists('ai_release_decisions');
        Schema::dropIfExists('ai_evaluation_results');
        Schema::dropIfExists('ai_evaluation_runs');
        Schema::dropIfExists('ai_evaluation_cases');
        Schema::dropIfExists('ai_evaluation_versions');
        Schema::dropIfExists('ai_evaluation_suites');
        Schema::dropIfExists('ai_handoffs');
    }
};
