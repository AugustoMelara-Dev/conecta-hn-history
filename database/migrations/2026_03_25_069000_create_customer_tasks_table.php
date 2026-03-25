<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->foreignId('customer_note_id')->nullable()->constrained('customer_notes')->nullOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('task_type', 32)->default('follow_up');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->string('source_channel', 32)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'sla_due_at'], 'customer_tasks_tenant_status_sla_idx');
            $table->index(['tenant_id', 'assigned_user_id', 'status'], 'customer_tasks_tenant_owner_status_idx');
            $table->index(['tenant_id', 'location_id', 'status'], 'customer_tasks_tenant_location_status_idx');
            $table->index(['customer_profile_id', 'created_at'], 'customer_tasks_profile_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tasks');
    }
};
