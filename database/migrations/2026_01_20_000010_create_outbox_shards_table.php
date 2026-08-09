<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LEVEL 47: Dynamic Shard Leasing Table
 *
 * Creates the lease ledger for autonomous worker coordination.
 * Workers acquire shards dynamically without hardcoded configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Create shard lease table (skip if exists)
        if (! Schema::hasTable('outbox_shards')) {
            Schema::create('outbox_shards', function (Blueprint $table) {
                $table->unsignedSmallInteger('shard_id')->primary()
                    ->comment('Partition shard ID (0 to PARTITION_COUNT-1)');

                $table->string('owner_worker_id', 64)->nullable()
                    ->comment('Worker process identifier that owns this shard');

                $table->timestamp('expires_at')->nullable()
                    ->comment('Lease expiration time, null = available');

                $table->timestamp('acquired_at')->nullable()
                    ->comment('When the lease was acquired');

                $table->unsignedInteger('messages_processed')->default(0)
                    ->comment('Messages processed by current owner');

                $table->timestamps();

                // Index for finding available shards
                $table->index(['expires_at'], 'idx_outbox_shards_expiry');
            });

            // 3. Pre-populate shard table with 64 shards (only if just created)
            $shards = [];
            for ($i = 0; $i < 64; $i++) {
                $shards[] = [
                    'shard_id' => $i,
                    'owner_worker_id' => null,
                    'expires_at' => null,
                    'acquired_at' => null,
                    'messages_processed' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('outbox_shards')->insert($shards);
        }

        // 2. Add quarantine columns to outbox_messages
        if (Schema::hasTable('outbox_messages')) {
            Schema::table('outbox_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('outbox_messages', 'quarantined_at')) {
                    $table->timestamp('quarantined_at')->nullable()
                        ->comment('When the message was quarantined as a poison pill');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_shards');

        Schema::table('outbox_messages', function (Blueprint $table) {
            if (Schema::hasColumn('outbox_messages', 'quarantined_at')) {
                $table->dropColumn('quarantined_at');
            }
        });
    }
};
