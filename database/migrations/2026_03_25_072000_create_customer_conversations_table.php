<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_conversations')) {
            return;
        }

        Schema::create('customer_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('conversation_key', 120);
            $table->string('source_channel', 32)->default('crm');
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->unsignedSmallInteger('message_count')->default(0);
            $table->unsignedSmallInteger('internal_note_count')->default(0);
            $table->unsignedSmallInteger('reply_count')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_team_message_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'conversation_key'], 'customer_conversations_tenant_key_unique');
            $table->index(['tenant_id', 'status', 'response_due_at'], 'customer_conversations_tenant_status_sla_idx');
            $table->index(['tenant_id', 'owner_user_id', 'status'], 'customer_conversations_tenant_owner_status_idx');
            $table->index(['tenant_id', 'location_id', 'status'], 'customer_conversations_tenant_location_status_idx');
            $table->index(['customer_profile_id', 'updated_at'], 'customer_conversations_profile_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_conversations');
    }
};
