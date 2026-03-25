<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_notes', function (Blueprint $table): void {
            $table->foreignId('customer_conversation_id')
                ->nullable()
                ->after('customer_profile_id')
                ->constrained('customer_conversations')
                ->nullOnDelete();

            $table->index(['tenant_id', 'customer_conversation_id'], 'customer_notes_tenant_conversation_idx');
        });

        Schema::table('customer_touchpoints', function (Blueprint $table): void {
            $table->foreignId('customer_conversation_id')
                ->nullable()
                ->after('customer_profile_id')
                ->constrained('customer_conversations')
                ->nullOnDelete();

            $table->index(['tenant_id', 'customer_conversation_id'], 'customer_touchpoints_tenant_conversation_idx');
        });

        Schema::table('customer_tasks', function (Blueprint $table): void {
            $table->foreignId('customer_conversation_id')
                ->nullable()
                ->after('customer_profile_id')
                ->constrained('customer_conversations')
                ->nullOnDelete();

            $table->index(['tenant_id', 'customer_conversation_id'], 'customer_tasks_tenant_conversation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_tasks', function (Blueprint $table): void {
            $table->dropIndex('customer_tasks_tenant_conversation_idx');
            $table->dropConstrainedForeignId('customer_conversation_id');
        });

        Schema::table('customer_touchpoints', function (Blueprint $table): void {
            $table->dropIndex('customer_touchpoints_tenant_conversation_idx');
            $table->dropConstrainedForeignId('customer_conversation_id');
        });

        Schema::table('customer_notes', function (Blueprint $table): void {
            $table->dropIndex('customer_notes_tenant_conversation_idx');
            $table->dropConstrainedForeignId('customer_conversation_id');
        });
    }
};
