<?php

declare(strict_types=1);

namespace App\Models;

use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\TransitionReservationCommand;
use App\Events\Appointment\AppointmentCreated;
use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Appointment Model - Service Booking Record
 *
 * Represents a scheduled appointment linking:
 * - A Customer (User or guest via customer_name/phone)
 * - A Service (what is being performed)
 * - A ServiceProvider (who performs it)
 * - A Time Slot (when it happens)
 *
 * STATUS LIFECYCLE:
 * pending → confirmed → completed
 *        ↘ cancelled
 *
 * SECURITY: Uses BelongsToTenant for automatic tenant isolation.
 */
class Appointment extends Model
{
    use BelongsToTenant, HasFactory;

    // ═══════════════════════════════════════════════════════════════════════════
    // STATUS CONSTANTS
    // ═══════════════════════════════════════════════════════════════════════════
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'user_id',
        'service_id',
        'service_provider_id',
        'scheduled_at',
        'end_at',
        'status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_notes',
        'staff_notes',
        'reminder_sent_at',
        'location_id',
        'currency',
        'price_minor',
        'service_name_snapshot',
        'duration_minutes',
        'timezone',
        'local_date',
        'idempotency_key',
        'idempotency_hash',
        'source',
        'status_reason',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'no_show_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'end_at' => 'datetime',
        'local_date' => 'date',
        'price_minor' => 'integer',
        'duration_minutes' => 'integer',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'no_show_at' => 'datetime',
    ];

    /**
     * Eloquent event -> custom event mapping for notifications.
     */
    protected $dispatchesEvents = [
        'created' => AppointmentCreated::class,
    ];

    // ═══════════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * The customer who booked (nullable for walk-ins).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The service being performed.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The staff member performing the service.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get upcoming appointments.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>=', now())
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED])
            ->orderBy('scheduled_at');
    }

    /**
     * Get today's appointments.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_at', today());
    }

    /**
     * Get appointments for a specific date range.
     */
    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('scheduled_at', [$start, $end]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Get customer display name (User or guest).
     */
    public function getCustomerDisplayNameAttribute(): string
    {
        return $this->user?->name ?? $this->customer_name ?? 'Cliente';
    }

    /**
     * Get status label in Spanish.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_CONFIRMED => 'Confirmada',
            self::STATUS_CANCELLED => 'Cancelada',
            self::STATUS_COMPLETED => 'Completada',
            self::STATUS_NO_SHOW => 'No se presentó',
            default => $this->status,
        };
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_CONFIRMED => 'info',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_NO_SHOW => 'gray',
            default => 'secondary',
        };
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // BUSINESS LOGIC
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Confirm this appointment.
     */
    public function confirm(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        return $this->canonicalTransition(self::STATUS_CONFIRMED);
    }

    /**
     * Cancel this appointment.
     */
    public function cancel(): bool
    {
        if (! in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true)) {
            return false;
        }

        return $this->canonicalTransition(
            self::STATUS_CANCELLED,
            'Cancellation through deprecated model adapter',
        );
    }

    /**
     * Mark this appointment as completed.
     */
    public function complete(): bool
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }

        return $this->canonicalTransition(self::STATUS_COMPLETED);
    }

    /**
     * Check if appointment can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    /**
     * @deprecated Dispatch TransitionReservationCommand to CanonicalReservationWriter directly.
     */
    private function canonicalTransition(string $targetStatus, ?string $reason = null): bool
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new \RuntimeException('Authenticated actor is required for appointment transitions.');
        }
        $command = new TransitionReservationCommand(
            tenantId: (string) $this->tenant_id,
            actorId: (string) $actor->getKey(),
            appointmentId: (int) $this->getKey(),
            targetStatus: $targetStatus,
            reason: $reason,
        );
        $writer = app(CanonicalReservationWriter::class);
        if ($targetStatus === self::STATUS_CANCELLED) {
            $writer->cancel($command);
        } else {
            $writer->transition($command);
        }
        $this->refresh();

        return true;
    }
}
