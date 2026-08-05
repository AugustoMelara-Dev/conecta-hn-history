<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** SQLite rollback rebuilds governed tables with foreign keys disabled. */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('ai_evaluation_runs', function (Blueprint $table): void {
            $table->uuid('release_policy_id')->nullable();
            $table->uuid('baseline_run_id')->nullable();
            $table->char('release_policy_checksum', 64)->nullable();
            $table->char('evaluation_version_checksum', 64)->nullable();
            $table->char('candidate_checksum', 64)->nullable();
            $table->char('prompt_checksum', 64)->nullable();
            $table->char('provider_checksum', 64)->nullable();
            $table->char('model_checksum', 64)->nullable();
            $table->char('dataset_checksum', 64)->nullable();
            $table->string('evaluator_version', 64)->nullable();
            $table->char('evaluator_checksum', 64)->nullable();
            $table->string('artifact_sha', 128)->nullable();
            $table->char('request_checksum', 64)->nullable();
            $table->char('result_checksum', 64)->nullable();
            $table->unsignedInteger('deterministic_seed')->nullable();
            $table->json('aggregate_metrics')->nullable();
            $table->json('blocking_codes')->nullable();
            $table->unsignedInteger('blocking_failures')->nullable();

            $table->foreign(['tenant_id', 'release_policy_id'], 'aierun_release_policy_fk')
                ->references(['tenant_id', 'id'])->on('ai_release_policies')->restrictOnDelete();
            $table->foreign(['tenant_id', 'baseline_run_id'], 'aierun_baseline_run_fk')
                ->references(['tenant_id', 'id'])->on('ai_evaluation_runs')->restrictOnDelete();
            $table->unique(['tenant_id', 'request_checksum'], 'aierun_tenant_request_unique');
            $table->index(['tenant_id', 'result_checksum'], 'aierun_tenant_result_idx');
        });

        Schema::table('ai_evaluation_results', function (Blueprint $table): void {
            $table->char('input_checksum', 64)->nullable();
            $table->char('result_checksum', 64)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('cost_usd', 12, 6)->nullable();
            $table->string('diagnostic_code', 64)->nullable();

            $table->index(['tenant_id', 'result_checksum'], 'aieres_tenant_result_idx');
        });

        Schema::table('ai_release_decisions', function (Blueprint $table): void {
            $table->uuid('prompt_version_id')->nullable();
            $table->uuid('provider_version_id')->nullable();
            $table->uuid('model_version_id')->nullable();
            $table->char('release_policy_checksum', 64)->nullable();
            $table->char('evaluation_version_checksum', 64)->nullable();
            $table->char('prompt_checksum', 64)->nullable();
            $table->char('provider_checksum', 64)->nullable();
            $table->char('model_checksum', 64)->nullable();
            $table->char('dataset_checksum', 64)->nullable();
            $table->string('evaluator_version', 64)->nullable();
            $table->char('evaluator_checksum', 64)->nullable();
            $table->string('artifact_sha', 128)->nullable();
            $table->char('run_result_checksum', 64)->nullable();

            $table->foreign(['tenant_id', 'prompt_version_id'], 'aird_prompt_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_prompt_versions')->restrictOnDelete();
            $table->foreign('provider_version_id', 'aird_provider_version_fk')
                ->references('id')->on('ai_provider_versions')->restrictOnDelete();
            $table->foreign('model_version_id', 'aird_model_version_fk')
                ->references('id')->on('ai_model_versions')->restrictOnDelete();
            $table->unique(['tenant_id', 'evaluation_run_id'], 'aird_tenant_run_unique');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->downSqlite();

            return;
        }

        Schema::table('ai_release_decisions', function (Blueprint $table): void {
            // MySQL removes the original child index when the stricter unique
            // tenant/run index supersedes it; restore FK support before rollback.
            $table->index(['tenant_id', 'evaluation_run_id'], 'ai_release_decisions_run_fk');
        });
        Schema::table('ai_release_decisions', function (Blueprint $table): void {
            $table->dropUnique('aird_tenant_run_unique');
        });
        Schema::table('ai_release_decisions', function (Blueprint $table): void {
            $table->dropForeign('aird_prompt_version_fk');
            $table->dropForeign('aird_provider_version_fk');
            $table->dropForeign('aird_model_version_fk');
        });
        Schema::table('ai_release_decisions', function (Blueprint $table): void {
            $table->dropColumn([
                'prompt_version_id', 'provider_version_id', 'model_version_id',
                'release_policy_checksum', 'evaluation_version_checksum', 'prompt_checksum',
                'provider_checksum', 'model_checksum', 'dataset_checksum', 'evaluator_version',
                'evaluator_checksum', 'artifact_sha', 'run_result_checksum',
            ]);
        });

        Schema::table('ai_evaluation_results', function (Blueprint $table): void {
            $table->dropIndex('aieres_tenant_result_idx');
            $table->dropColumn([
                'input_checksum', 'result_checksum', 'latency_ms', 'cost_usd', 'diagnostic_code',
            ]);
        });

        Schema::table('ai_evaluation_runs', function (Blueprint $table): void {
            $table->dropUnique('aierun_tenant_request_unique');
            $table->dropIndex('aierun_tenant_result_idx');
        });
        Schema::table('ai_evaluation_runs', function (Blueprint $table): void {
            $table->dropForeign('aierun_release_policy_fk');
            $table->dropForeign('aierun_baseline_run_fk');
        });
        Schema::table('ai_evaluation_runs', function (Blueprint $table): void {
            $table->dropColumn([
                'release_policy_id', 'baseline_run_id', 'release_policy_checksum',
                'evaluation_version_checksum', 'candidate_checksum', 'prompt_checksum',
                'provider_checksum', 'model_checksum', 'dataset_checksum', 'evaluator_version',
                'evaluator_checksum', 'artifact_sha', 'request_checksum', 'result_checksum',
                'deterministic_seed', 'aggregate_metrics', 'blocking_codes', 'blocking_failures',
            ]);
        });
    }

    private function downSqlite(): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            $this->rebuildSqliteReleaseDecisions();
            $this->rebuildSqliteEvaluationResults();
            $this->rebuildSqliteEvaluationRuns();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function rebuildSqliteReleaseDecisions(): void
    {
        $temporary = 'ai_release_decisions_task10_rollback';
        Schema::create($temporary, function (Blueprint $table): void {
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
            $table->char('candidate_checksum', 64)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_by', 64)->nullable();
            $table->text('revocation_reason')->nullable();

            $table->foreign(['tenant_id', 'release_policy_id'], 'ai_release_decisions_policy_fk')
                ->references(['tenant_id', 'id'])->on('ai_release_policies')->restrictOnDelete();
            $table->foreign(['tenant_id', 'evaluation_run_id'], 'ai_release_decisions_run_fk')
                ->references(['tenant_id', 'id'])->on('ai_evaluation_runs')->restrictOnDelete();
            $table->foreign(['tenant_id', 'agent_version_id'], 'ai_release_decisions_agent_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
        });
        $columns = [
            'id', 'tenant_id', 'release_policy_id', 'evaluation_run_id', 'agent_version_id',
            'decision', 'decided_by', 'evidence_hash', 'rationale', 'decided_at', 'created_at',
            'updated_at', 'candidate_checksum', 'valid_from', 'valid_until', 'revoked_at',
            'revoked_by', 'revocation_reason',
        ];
        DB::table($temporary)->insertUsing($columns, DB::table('ai_release_decisions')->select($columns));
        Schema::drop('ai_release_decisions');
        Schema::rename($temporary, 'ai_release_decisions');
        Schema::table('ai_release_decisions', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id'], 'ai_release_decisions_tenant_id_unique');
            $table->index(['tenant_id', 'agent_version_id', 'decided_at'], 'ai_release_decisions_agent_idx');
            $table->index(['tenant_id', 'agent_version_id', 'valid_from'], 'aird_agent_valid_from_idx');
            $table->index(['tenant_id', 'revoked_at'], 'aird_tenant_revoked_idx');
        });
    }

    private function rebuildSqliteEvaluationResults(): void
    {
        $temporary = 'ai_evaluation_results_task10_rollback';
        Schema::create($temporary, function (Blueprint $table): void {
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
        });
        $columns = [
            'id', 'tenant_id', 'evaluation_run_id', 'evaluation_case_id', 'status', 'score_minor',
            'output_ciphertext', 'output_hash', 'encryption_key_version', 'metrics', 'failure_detail',
            'completed_at', 'created_at', 'updated_at',
        ];
        DB::table($temporary)->insertUsing($columns, DB::table('ai_evaluation_results')->select($columns));
        Schema::drop('ai_evaluation_results');
        Schema::rename($temporary, 'ai_evaluation_results');
        Schema::table('ai_evaluation_results', function (Blueprint $table): void {
            $table->unique(['evaluation_run_id', 'evaluation_case_id'], 'ai_evaluation_results_run_case_unique');
            $table->index(['tenant_id', 'evaluation_run_id'], 'ai_evaluation_results_tenant_run_idx');
        });
    }

    private function rebuildSqliteEvaluationRuns(): void
    {
        $temporary = 'ai_evaluation_runs_task10_rollback';
        Schema::create($temporary, function (Blueprint $table): void {
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
        });
        $columns = [
            'id', 'tenant_id', 'evaluation_version_id', 'agent_version_id', 'prompt_version_id',
            'provider_version_id', 'model_version_id', 'status', 'triggered_by', 'started_at',
            'completed_at', 'created_at', 'updated_at',
        ];
        DB::table($temporary)->insertUsing($columns, DB::table('ai_evaluation_runs')->select($columns));
        Schema::drop('ai_evaluation_runs');
        Schema::rename($temporary, 'ai_evaluation_runs');
        Schema::table('ai_evaluation_runs', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id'], 'ai_evaluation_runs_tenant_id_unique');
            $table->index(['tenant_id', 'status', 'created_at'], 'ai_evaluation_runs_tenant_status_idx');
        });
    }
};
