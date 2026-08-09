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
        if (! Schema::hasTable('outbox_messages')) {
            return;
        }

        Schema::table('outbox_messages', function (Blueprint $table) {
            // LEVEL 42: Add locked_at for zombie lock prevention
            if (! Schema::hasColumn('outbox_messages', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()
                    ->comment('Timestamp when worker claimed this message');
            }
        });

        // Add index only if it doesn't exist (check via raw query for idempotence)
        $indexExists = collect(Schema::getIndexes('outbox_messages'))
            ->contains(fn ($idx) => $idx['name'] === 'idx_outbox_worker_query');

        if (! $indexExists) {
            Schema::table('outbox_messages', function (Blueprint $table) {
                $table->index(['status', 'locked_at'], 'idx_outbox_worker_query');
            });
        }
    }

    public function down(): void
    {
        Schema::table('outbox_messages', function (Blueprint $table) {
            $table->dropIndex('idx_outbox_worker_query');
            $table->dropColumn('locked_at');
        });
    }
};
