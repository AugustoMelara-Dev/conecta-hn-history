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
        Schema::table('ai_handoffs', function (Blueprint $table): void {
            $table->string('trigger_type', 32)->nullable();
            $table->string('priority', 16)->nullable();
            $table->string('queue_key', 128)->nullable();
            $table->char('active_deduplication_key', 64)->nullable();
            $table->uuid('assigned_to_user_id')->nullable();
            $table->uuid('assigned_by_user_id')->nullable();
            $table->uuid('accepted_by_user_id')->nullable();
            $table->uuid('resolved_by_user_id')->nullable();
            $table->uuid('agent_version_id')->nullable();
            $table->unsignedInteger('version')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->longText('resolution_ciphertext')->nullable();
            $table->char('resolution_hash', 64)->nullable();
            $table->string('resolution_key_version', 32)->nullable();
            $table->boolean('return_to_agent')->nullable();
        });

        $rows = DB::table('ai_handoffs')
            ->orderBy('tenant_id')
            ->orderBy('conversation_id')
            ->orderBy('requested_at')
            ->orderBy('id')
            ->get();
        $activeOwners = [];
        foreach ($rows as $row) {
            $status = match ((string) $row->status) {
                'pending', 'requested' => 'queued',
                'completed' => 'resolved',
                default => (string) $row->status,
            };
            $active = in_array($status, ['queued', 'assigned', 'accepted'], true);
            $identity = strtolower((string) $row->tenant_id).'|'.strtolower((string) $row->conversation_id);
            if ($active && isset($activeOwners[$identity])) {
                $status = 'cancelled';
                $active = false;
            }
            if ($active) {
                $activeOwners[$identity] = true;
            }
            $agentVersionId = DB::table('ai_conversations')
                ->where('tenant_id', $row->tenant_id)
                ->where('id', $row->conversation_id)
                ->value('agent_version_id');
            DB::table('ai_handoffs')->where('id', $row->id)->update([
                'status' => $status,
                'trigger_type' => 'merchant_rule',
                'priority' => 'normal',
                'queue_key' => 'legacy-support',
                'active_deduplication_key' => $active ? hash('sha256', $identity) : null,
                'agent_version_id' => is_string($agentVersionId) ? $agentVersionId : null,
                'version' => 1,
                'queued_at' => $row->requested_at,
                'resolved_at' => $status === 'resolved' ? ($row->completed_at ?? $row->updated_at) : null,
                'cancelled_at' => $status === 'cancelled' ? $row->updated_at : null,
                'return_to_agent' => false,
            ]);
        }

        Schema::table('ai_handoffs', function (Blueprint $table): void {
            $table->foreign('assigned_to_user_id', 'ai_handoffs_assigned_to_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_by_user_id', 'ai_handoffs_assigned_by_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('accepted_by_user_id', 'ai_handoffs_accepted_by_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('resolved_by_user_id', 'ai_handoffs_resolved_by_user_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['tenant_id', 'agent_version_id'], 'ai_handoffs_agent_version_fk')
                ->references(['tenant_id', 'id'])->on('ai_agent_versions')->restrictOnDelete();
            $table->unique(
                ['tenant_id', 'active_deduplication_key'],
                'ai_handoffs_tenant_active_dedup_unique',
            );
            $table->index(
                ['tenant_id', 'status', 'queue_key', 'queued_at'],
                'ai_handoffs_tenant_queue_status_queued_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_handoffs', function (Blueprint $table): void {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['tenant_id', 'agent_version_id']);
                $table->dropForeign(['resolved_by_user_id']);
                $table->dropForeign(['accepted_by_user_id']);
                $table->dropForeign(['assigned_by_user_id']);
                $table->dropForeign(['assigned_to_user_id']);
            } else {
                $table->dropForeign('ai_handoffs_agent_version_fk');
                $table->dropForeign('ai_handoffs_resolved_by_user_fk');
                $table->dropForeign('ai_handoffs_accepted_by_user_fk');
                $table->dropForeign('ai_handoffs_assigned_by_user_fk');
                $table->dropForeign('ai_handoffs_assigned_to_user_fk');
            }
            $table->dropUnique('ai_handoffs_tenant_active_dedup_unique');
            $table->dropIndex('ai_handoffs_tenant_queue_status_queued_idx');
            $table->dropColumn([
                'trigger_type', 'priority', 'queue_key', 'active_deduplication_key',
                'assigned_to_user_id', 'assigned_by_user_id', 'accepted_by_user_id',
                'resolved_by_user_id', 'agent_version_id', 'version', 'queued_at',
                'assigned_at', 'resolved_at', 'cancelled_at', 'expired_at',
                'resolution_ciphertext', 'resolution_hash', 'resolution_key_version',
                'return_to_agent',
            ]);
        });
    }
};
