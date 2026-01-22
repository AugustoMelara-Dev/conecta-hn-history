<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LEVEL 46: Atomic Sequence Table & Enhanced Outbox Schema
 *
 * Creates dedicated sequence table for O(1) atomic sequence generation.
 * Adds recovery_count to outbox_messages for stale lock tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Create atomic sequence table
        Schema::create('outbox_sequences', function (Blueprint $table) {
            $table->string('aggregate_id', 36)->primary()
                ->comment('Business entity ID (e.g., payment_id)');

            $table->unsignedBigInteger('last_sequence')->default(0)
                ->comment('Last assigned sequence number for this aggregate');

            $table->timestamps();

            // Engine hint for InnoDB row-level locking
            $table->engine = 'InnoDB';
        });

        // 2. Add recovery_count to outbox_messages
        Schema::table('outbox_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('outbox_messages', 'recovery_count')) {
                $table->unsignedSmallInteger('recovery_count')->default(0)->after('attempts')
                    ->comment('Number of times this message was recovered from stale lock');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_sequences');

        Schema::table('outbox_messages', function (Blueprint $table) {
            if (Schema::hasColumn('outbox_messages', 'recovery_count')) {
                $table->dropColumn('recovery_count');
            }
        });
    }
};
