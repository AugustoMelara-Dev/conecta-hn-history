<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create processed_events table for idempotency deduplication.
 *
 * PURPOSE:
 *   Queue delivery is "at-least-once". Without tracking which events
 *   have been processed, retries can cause:
 *     - Duplicate invoices
 *     - Double charges
 *     - Spam emails
 *
 * USAGE IN LISTENERS:
 *   if (ProcessedEvent::exists($event->eventId(), static::class)) {
 *       return; // Already processed, skip
 *   }
 *
 *   // ... do work ...
 *
 *   ProcessedEvent::markAsProcessed($event->eventId(), static::class);
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_events', function (Blueprint $table) {
            // UUID of the event (from DomainEvent::eventId())
            $table->uuid('event_id');

            // Fully qualified class name of the listener
            // Different listeners can process the same event
            $table->string('listener_class', 255);

            // When the event was processed
            $table->timestamp('processed_at')->useCurrent();

            // Composite primary key for fast lookups
            $table->primary(['event_id', 'listener_class']);

            // Index for cleanup jobs (delete old entries)
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
    }
};
