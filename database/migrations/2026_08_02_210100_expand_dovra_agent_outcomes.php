<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dovra_agent_action_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('agent_version_id')->index();
            $table->uuid('conversation_id')->index();
            $table->string('action_key', 128);
            $table->string('idempotency_key', 160);
            $table->char('input_hash', 64);
            $table->string('risk', 16);
            $table->string('policy_code', 64);
            $table->uuid('confirmation_id')->nullable()->index();
            $table->uuid('workflow_run_id')->nullable()->index();
            $table->uuid('outcome_id')->nullable()->index();
            $table->string('status', 32);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'action_key', 'idempotency_key'], 'dovra_agent_action_idempotency_unique');
            $table->index(['tenant_id', 'agent_version_id', 'created_at'], 'dovra_agent_action_audits_agent_idx');
            $table->index(['tenant_id', 'conversation_id', 'created_at'], 'dovra_agent_action_audits_conversation_idx');
        });

        Schema::create('dovra_agent_outcomes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('audit_id')->index();
            $table->uuid('agent_version_id')->index();
            $table->uuid('conversation_id')->index();
            $table->string('action_key', 128);
            $table->string('outcome_type', 64);
            $table->string('aggregate_type', 64)->nullable();
            $table->string('aggregate_id', 128)->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('verified_commercial_outcome_id')->nullable()->index();
            $table->json('attribution_metadata')->nullable();
            $table->char('payload_hash', 64);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign('audit_id')->references('id')->on('dovra_agent_action_audits')->cascadeOnDelete();
            $table->index(['tenant_id', 'outcome_type', 'occurred_at'], 'dovra_agent_outcomes_type_idx');
            $table->index(['tenant_id', 'aggregate_type', 'aggregate_id'], 'dovra_agent_outcomes_aggregate_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['dovra_agent_action_audits', 'dovra_agent_outcomes'] as $table) {
                DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE \"{$table}\" FORCE ROW LEVEL SECURITY");
                DB::statement(
                    "CREATE POLICY \"{$table}_tenant_isolation\" ON \"{$table}\" "
                    ."USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid) "
                    ."WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)",
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dovra_agent_outcomes');
        Schema::dropIfExists('dovra_agent_action_audits');
    }
};
