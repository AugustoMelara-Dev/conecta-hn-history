<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * ProcessedEvent - Idempotency Guard for Event Listeners
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PREVENTS DUPLICATE PROCESSING                                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PROBLEM:                                                               ║
 * ║    Queue delivery is "at-least-once". Network retries can deliver       ║
 * ║    the same OrderPaid event twice → 2 invoices, 2 charges.              ║
 * ║                                                                          ║
 * ║  SOLUTION:                                                              ║
 * ║    Each listener checks if it already processed this specific event.   ║
 * ║                                                                          ║
 * ║  USAGE IN LISTENERS:                                                    ║
 * ║                                                                          ║
 * ║    public function handle(OrderPaid $event): void                       ║
 * ║    {                                                                    ║
 * ║        if (ProcessedEvent::wasProcessed($event->eventId(), self::class)) {║
 * ║            Log::info('Duplicate event, skipping');                      ║
 * ║            return;                                                      ║
 * ║        }                                                                ║
 * ║                                                                          ║
 * ║        // ... do work ...                                               ║
 * ║                                                                          ║
 * ║        ProcessedEvent::markProcessed($event->eventId(), self::class);  ║
 * ║    }                                                                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @property string $event_id
 * @property string $listener_class
 * @property \Carbon\Carbon $processed_at
 */
class ProcessedEvent extends Model
{
    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     */
    protected $table = 'processed_events';

    /**
     * The primary key is composite, so we disable auto-increment.
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'event_id',
        'listener_class',
        'processed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'processed_at' => 'datetime',
    ];

    /**
     * Check if this event was already processed by this listener.
     */
    public static function wasProcessed(string $eventId, string $listenerClass): bool
    {
        return self::where('event_id', $eventId)
            ->where('listener_class', $listenerClass)
            ->exists();
    }

    /**
     * Mark this event as processed by this listener.
     * 
     * Uses INSERT IGNORE to handle race conditions.
     */
    public static function markProcessed(string $eventId, string $listenerClass): void
    {
        // Use insertOrIgnore for race condition safety
        DB::table('processed_events')->insertOrIgnore([
            'event_id' => $eventId,
            'listener_class' => $listenerClass,
            'processed_at' => now(),
        ]);
    }

    /**
     * Clean up old entries (call from scheduled job).
     * 
     * @param int $daysOld Delete entries older than this many days
     */
    public static function cleanup(int $daysOld = 30): int
    {
        return self::where('processed_at', '<', now()->subDays($daysOld))
            ->delete();
    }
}
