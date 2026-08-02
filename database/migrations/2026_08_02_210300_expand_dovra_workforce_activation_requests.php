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
        Schema::create('dovra_workforce_activation_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('idempotency_key', 160);
            $table->char('request_hash', 64);
            $table->string('role_key', 32);
            $table->string('approval_mode', 32);
            $table->json('channel_keys')->nullable();
            $table->json('knowledge_source_ids')->nullable();
            $table->json('simulation_case_ids')->nullable();
            $table->uuid('candidate_agent_version_id')->nullable();
            $table->uuid('release_decision_id')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->string('state', 48);
            $table->json('response');
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'dovra_workforce_activation_idempotency_unique');
            $table->index(['tenant_id', 'role_key', 'state'], 'dovra_workforce_activation_role_state_idx');
            $table->foreign(['tenant_id', 'candidate_agent_version_id'], 'dovra_workforce_activation_candidate_fk')
                ->references(['tenant_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
            $table->foreign(['tenant_id', 'release_decision_id'], 'dovra_workforce_activation_release_fk')
                ->references(['tenant_id', 'id'])->on('ai_release_decisions')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "dovra_workforce_activation_requests" ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "dovra_workforce_activation_requests" FORCE ROW LEVEL SECURITY');
            DB::statement(
                'CREATE POLICY "dovra_workforce_activation_requests_tenant_isolation" '
                .'ON "dovra_workforce_activation_requests" '
                .'USING (tenant_id = NULLIF(current_setting(\'app.tenant_id\', true), \'\')::uuid) '
                .'WITH CHECK (tenant_id = NULLIF(current_setting(\'app.tenant_id\', true), \'\')::uuid)',
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS "dovra_workforce_activation_requests_tenant_isolation" ON "dovra_workforce_activation_requests"');
            DB::statement('ALTER TABLE "dovra_workforce_activation_requests" NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "dovra_workforce_activation_requests" DISABLE ROW LEVEL SECURITY');
        }

        Schema::dropIfExists('dovra_workforce_activation_requests');
    }
};
