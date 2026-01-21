<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LEVEL 42 TRUE: Outbox Hardening Migration
 * 
 * Adds locked_at column to prevent "Zombified" locks and supports
 * the PROCESSING state for multi-worker coordination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            // LEVEL 42: Add locked_at for zombie lock prevention
            if (!Schema::hasColumn('outbox_messages', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('status')
                    ->comment('Timestamp when worker claimed this message');
            }
            
            // Add index for efficient worker queries
            $table->index(['status', 'locked_at'], 'idx_outbox_worker_query');
        });
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropIndex('idx_outbox_worker_query');
            $table->dropColumn('locked_at');
        });
    }
};
