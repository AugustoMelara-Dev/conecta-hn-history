<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_conversations', function (Blueprint $table): void {
            $table->string('assignment_group', 32)->nullable()->after('location_id');
            $table->unsignedSmallInteger('response_sla_minutes')->nullable()->after('priority');
            $table->timestamp('escalation_due_at')->nullable()->after('response_due_at');

            $table->index(['tenant_id', 'assignment_group', 'status'], 'customer_conversations_tenant_group_status_idx');
            $table->index(['tenant_id', 'escalation_due_at'], 'customer_conversations_tenant_escalation_idx');
        });

        Schema::table('customer_tasks', function (Blueprint $table): void {
            $table->string('assignment_group', 32)->nullable()->after('location_id');
            $table->unsignedSmallInteger('response_sla_minutes')->nullable()->after('priority');

            $table->index(['tenant_id', 'assignment_group', 'status'], 'customer_tasks_tenant_group_status_idx');
        });

        Schema::table('customer_conversation_messages', function (Blueprint $table): void {
            $table->string('provider_message_id', 160)->nullable()->after('status');
            $table->string('provider_thread_key', 160)->nullable()->after('provider_message_id');
            $table->string('provider_reply_to', 160)->nullable()->after('provider_thread_key');

            $table->index(['tenant_id', 'provider_thread_key'], 'customer_conversation_messages_tenant_thread_idx');
            $table->index(['tenant_id', 'provider_message_id'], 'customer_conversation_messages_tenant_provider_msg_idx');
        });

        Schema::table('customer_journey_runs', function (Blueprint $table): void {
            $table->timestamp('current_step_executed_at')->nullable()->after('step_due_at');
            $table->unsignedSmallInteger('auto_step_count')->default(0)->after('current_step_executed_at');
            $table->foreignId('last_auto_touchpoint_id')->nullable()->after('auto_step_count')->constrained('customer_touchpoints')->nullOnDelete();

            $table->index(['tenant_id', 'sequence_state', 'step_due_at'], 'customer_journey_runs_tenant_sequence_step_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_journey_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_auto_touchpoint_id');
            $table->dropIndex('customer_journey_runs_tenant_sequence_step_due_idx');
            $table->dropColumn([
                'current_step_executed_at',
                'auto_step_count',
            ]);
        });

        Schema::table('customer_conversation_messages', function (Blueprint $table): void {
            $table->dropIndex('customer_conversation_messages_tenant_thread_idx');
            $table->dropIndex('customer_conversation_messages_tenant_provider_msg_idx');
            $table->dropColumn([
                'provider_message_id',
                'provider_thread_key',
                'provider_reply_to',
            ]);
        });

        Schema::table('customer_tasks', function (Blueprint $table): void {
            $table->dropIndex('customer_tasks_tenant_group_status_idx');
            $table->dropColumn([
                'assignment_group',
                'response_sla_minutes',
            ]);
        });

        Schema::table('customer_conversations', function (Blueprint $table): void {
            $table->dropIndex('customer_conversations_tenant_group_status_idx');
            $table->dropIndex('customer_conversations_tenant_escalation_idx');
            $table->dropColumn([
                'assignment_group',
                'response_sla_minutes',
                'escalation_due_at',
            ]);
        });
    }
};
