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
        // CANONICAL SCHEMA: This is THE definitive structure.
        // All subsequent migrations should use hasColumn checks.
        if (! Schema::hasTable('outbox_messages')) {
            Schema::create('outbox_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type'); // Event FQCN
                $table->json('payload'); // Event Data
                $table->timestamp('occurred_at')->nullable();
                $table->timestamp('published_at')->nullable()->index();
                $table->string('status')->default('PENDING')->index();
                $table->timestamps();

                // Critical Index for Polling Performance
                $table->index(['status', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('idempotency_keys');
    }
};
