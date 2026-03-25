<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_conversation_id')->constrained('customer_conversations')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_touchpoint_id')->nullable()->constrained('customer_touchpoints')->nullOnDelete();
            $table->foreignId('customer_note_id')->nullable()->constrained('customer_notes')->nullOnDelete();
            $table->string('direction', 16)->default('team');
            $table->string('message_kind', 32)->default('message');
            $table->string('channel', 32)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_conversation_id', 'occurred_at'], 'customer_conversation_messages_conversation_occ_idx');
            $table->index(['tenant_id', 'direction', 'occurred_at'], 'customer_conversation_messages_tenant_direction_occ_idx');
            $table->index(['tenant_id', 'message_kind', 'occurred_at'], 'customer_conversation_messages_tenant_kind_occ_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_conversation_messages');
    }
};
