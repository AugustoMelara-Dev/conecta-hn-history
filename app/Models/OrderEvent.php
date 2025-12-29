<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrderEvent - Event Sourcing Model for Order Lifecycle
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  WRITE-ONLY: This is an append-only ledger, never update or delete      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 * - Recording: OrderEvent::record($order, 'status_changed', $payload, $actor)
 * - Querying: $order->events()->where('event_type', 'status_changed')->get()
 * - Analytics: OrderEvent::whereBetween('occurred_at', [$start, $end])->count()
 *
 * @property int $id
 * @property int $order_id
 * @property string $event_type
 * @property array|null $payload
 * @property int|null $actor_id
 * @property \Carbon\Carbon $occurred_at
 */
class OrderEvent extends Model
{
    /**
     * Disable timestamps - we use occurred_at instead.
     */
    public $timestamps = false;

    /**
     * Allow mass assignment for factory method.
     */
    protected $guarded = [];

    /**
     * Attribute casting for type safety.
     */
    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // FACTORY METHOD (Preferred over direct creation)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Record a new event in the order's history.
     *
     * @param  Order  $order  The order this event belongs to
     * @param  string  $eventType  Event category (e.g., 'status_changed')
     * @param  array  $payload  Flexible metadata for the event
     * @param  User|int|null  $actor  User who triggered the event (null = system)
     * @return static The created event
     */
    public static function record(
        Order $order,
        string $eventType,
        array $payload = [],
        User|int|null $actor = null
    ): static {
        return static::create([
            'order_id' => $order->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'actor_id' => $actor instanceof User ? $actor->id : $actor,
            'occurred_at' => now(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get the order this event belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who triggered this event.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get human-readable event type label.
     */
    public function getEventTypeLabelAttribute(): string
    {
        return match ($this->event_type) {
            'created' => 'Orden Creada',
            'status_changed' => 'Cambio de Estado',
            'payment_verified' => 'Pago Verificado',
            'cancelled' => 'Orden Cancelada',
            'restored' => 'Orden Restaurada',
            default => ucfirst(str_replace('_', ' ', $this->event_type)),
        };
    }

    /**
     * Check if this was a system-generated event.
     */
    public function getIsSystemEventAttribute(): bool
    {
        return $this->actor_id === null;
    }
}
