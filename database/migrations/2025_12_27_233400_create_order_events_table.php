<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Order Events Table (Event Sourcing Schema)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IMMUTABLE AUDIT LEDGER: Write-Only Order Lifecycle Events              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * PURPOSE:
 * - Non-Repudiation: Prove exactly who changed what and when
 * - Dispute Resolution: Full history for customer service
 * - Analytics: Aggregate transition times (pending → confirmed latency)
 * - Replay: Reconstruct order state at any point in history
 *
 * EVENT TYPES:
 * - 'created': Order created (payload: initial data)
 * - 'status_changed': FSM transition (payload: from, to, reason)
 * - 'payment_verified': Payment confirmed (payload: transaction_id)
 * - 'cancelled': Order cancelled (payload: reason, actor)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════════════
            // PRIMARY KEY
            // ═══════════════════════════════════════════════════════════════
            $table->id();

            // ═══════════════════════════════════════════════════════════════
            // FOREIGN KEY - Order Reference
            // ═══════════════════════════════════════════════════════════════
            // CASCADE DELETE: If order is purged, events go with it
            // This maintains referential integrity while allowing cleanup
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // ═══════════════════════════════════════════════════════════════
            // EVENT METADATA
            // ═══════════════════════════════════════════════════════════════
            $table->string('event_type', 50)
                ->comment('Event category: created, status_changed, payment_verified, cancelled');

            // ═══════════════════════════════════════════════════════════════
            // FLEXIBLE PAYLOAD (Schema-less metadata)
            // ═══════════════════════════════════════════════════════════════
            // JSON allows storing arbitrary event-specific data:
            // - status_changed: { from: 'pending', to: 'confirmed', actor_id: 5 }
            // - payment_verified: { transaction_id: 'txn_abc123', gateway: 'stripe' }
            // - cancelled: { reason: 'customer_request', refund_amount: 150.00 }
            $table->json('payload')->nullable();

            // ═══════════════════════════════════════════════════════════════
            // ACTOR TRACKING (Who triggered this event?)
            // ═══════════════════════════════════════════════════════════════
            // NULL = system-generated event (e.g., auto-cancel after timeout)
            $table->foreignUuid('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ═══════════════════════════════════════════════════════════════
            // TIMESTAMP (Immutable record of when event occurred)
            // ═══════════════════════════════════════════════════════════════
            // Using dedicated column instead of created_at for semantic clarity
            $table->timestamp('occurred_at')->useCurrent();

            // ═══════════════════════════════════════════════════════════════
            // INDEXES for Query Performance
            // ═══════════════════════════════════════════════════════════════
            // Composite index for order history queries
            $table->index(['order_id', 'occurred_at'], 'order_events_history_idx');
            // Filter by event type across all orders
            $table->index('event_type', 'order_events_type_idx');
            // Analytics: event timeline queries
            $table->index('occurred_at', 'order_events_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
