<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create webhook_logs table for payment webhook idempotency and audit trail.
 *
 * CRITICAL: Unique compound index on (provider, external_event_id) ensures
 * idempotency at database level even if application-level check fails.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index();
            $table->string('external_event_id', 255);
            $table->string('event_type', 100)->index();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            // IDEMPOTENCY GUARD: Database-level uniqueness
            $table->unique(['provider', 'external_event_id'], 'webhook_idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
