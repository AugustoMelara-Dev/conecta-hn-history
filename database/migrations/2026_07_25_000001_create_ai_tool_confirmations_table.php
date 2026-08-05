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
        Schema::create('ai_tool_confirmations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('tool', 100);
            $table->text('payload');
            $table->char('payload_hash', 64);
            $table->text('confirmation_token');
            $table->char('confirmation_token_hash', 64);
            $table->string('idempotency_key', 100);
            $table->string('status', 20)->default('pending');
            $table->text('result')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'actor_id', 'idempotency_key'],
                'ai_tool_confirmations_actor_idempotency_unique',
            );
            $table->index(['tenant_id', 'status', 'expires_at'], 'ai_tool_confirmations_pending_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "ai_tool_confirmations" ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "ai_tool_confirmations" FORCE ROW LEVEL SECURITY');
            DB::statement(
                'CREATE POLICY "ai_tool_confirmations_tenant_isolation" ON "ai_tool_confirmations" '
                ."USING (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid) "
                ."WITH CHECK (tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_confirmations');
    }
};
