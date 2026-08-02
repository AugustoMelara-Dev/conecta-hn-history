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
        Schema::create('dovra_conversation_channel_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('conversation_id');
            $table->string('channel', 32);
            $table->string('connection_key', 64);
            $table->char('normalized_identity_hash', 64);
            $table->string('external_thread_reference', 500)->nullable();
            $table->string('external_message_id', 160)->nullable();
            $table->string('provider_event_id', 160)->nullable();
            $table->string('consent_purpose', 64);
            $table->string('consent_status', 32)->nullable();
            $table->string('lineage_idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'conversation_id'], 'dovra_channel_links_conversation_fk')
                ->references(['tenant_id', 'id'])->on('ai_conversations')->cascadeOnDelete();
            $table->unique(
                ['tenant_id', 'channel', 'connection_key', 'external_message_id'],
                'dovra_channel_links_inbound_unique',
            );
            $table->unique(
                ['tenant_id', 'channel', 'connection_key', 'lineage_idempotency_key'],
                'dovra_channel_links_lineage_unique',
            );
            $table->index(
                ['tenant_id', 'channel', 'connection_key', 'provider_event_id'],
                'dovra_channel_links_provider_event_idx',
            );
            $table->index(
                ['tenant_id', 'normalized_identity_hash', 'consent_status'],
                'dovra_channel_links_identity_consent_idx',
            );
            $table->index(
                ['tenant_id', 'conversation_id', 'channel'],
                'dovra_channel_links_conversation_idx',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "dovra_conversation_channel_links" ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "dovra_conversation_channel_links" FORCE ROW LEVEL SECURITY');
            DB::statement(
                'CREATE POLICY "dovra_conversation_channel_links_tenant_isolation" '
                .'ON "dovra_conversation_channel_links" '
                .'USING (tenant_id = NULLIF(current_setting(\'app.tenant_id\', true), \'\')::uuid) '
                .'WITH CHECK (tenant_id = NULLIF(current_setting(\'app.tenant_id\', true), \'\')::uuid)',
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS "dovra_conversation_channel_links_tenant_isolation" ON "dovra_conversation_channel_links"');
            DB::statement('ALTER TABLE "dovra_conversation_channel_links" NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "dovra_conversation_channel_links" DISABLE ROW LEVEL SECURITY');
        }

        Schema::dropIfExists('dovra_conversation_channel_links');
    }
};
