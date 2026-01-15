<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table 1: Idempotency Keys (Concurrency Control)
        if (! Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $table) {
                $table->string('key', 64)->primary(); // Checksum/UUID
                $table->json('response_payload'); // Serialized Result
                $table->timestamps();
                
                // Optimization: Index for cleanup
                $table->index('created_at');
            });
        }

        // Table 2: Outbox Messages (Reliable Events)
        if (! Schema::hasTable('outbox_messages')) {
            Schema::create('outbox_messages', function (Blueprint $table) {
                $table->id();
                $table->string('type'); // Event FQCN
                $table->json('payload'); // Event Data
                $table->string('status')->default('PENDING')->index(); // StatusStateMachine
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('idempotency_keys');
    }
};
