<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional Outbox Pattern - Message Storage
 *
 * This table stores domain events atomically within the same transaction
 * as the business operation. A separate worker process reads and dispatches
 * these events, guaranteeing at-least-once delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_outbox_messages', function (Blueprint $table) {
            // UUID for distributed system compatibility (no lock contention)
            $table->uuid('id')->primary();
            
            // Event type (FQCN)
            $table->string('type', 255)->index();
            
            // Aggregate information for debugging/filtering
            $table->string('aggregate_type', 255)->nullable();
            $table->string('aggregate_id', 255)->nullable();
            
            // Serialized event payload
            $table->json('payload');
            
            // Event timing (microseconds precision)
            $table->timestamp('occurred_at', 6)->useCurrent();
            
            // Publication tracking (NULL = pending)
            $table->timestamp('published_at', 6)->nullable();
            
            // Error tracking for failed deliveries
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            
            // Composite index for worker queries
            $table->index(['published_at', 'occurred_at'], 'outbox_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_outbox_messages');
    }
};
