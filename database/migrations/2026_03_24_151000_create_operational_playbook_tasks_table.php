<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_playbook_tasks')) {
            return;
        }

        Schema::create('operational_playbook_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger_key', 64);
            $table->string('queue', 32);
            $table->string('route_key', 32)->nullable();
            $table->string('segment_key', 64)->nullable();
            $table->string('suggested_preset_key', 64)->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 16)->default('open');
            $table->unsignedInteger('sla_minutes')->default(60);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('playbook_steps')->nullable();
            $table->string('related_type')->nullable();
            $table->string('related_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'due_at'], 'playbook_tasks_tenant_status_due_idx');
            $table->index(['tenant_id', 'queue', 'priority'], 'playbook_tasks_tenant_queue_priority_idx');
            $table->index(['tenant_id', 'trigger_key', 'related_type', 'related_id'], 'playbook_tasks_dedupe_idx');
            $table->index(['assigned_user_id', 'status'], 'playbook_tasks_assignee_status_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('operational_playbook_tasks')) {
            Schema::drop('operational_playbook_tasks');
        }
    }
};
