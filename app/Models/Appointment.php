<?php

declare(strict_types=1);

namespace App\Models;

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
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    /**
     * Eloquent event -> custom event mapping for notifications.
     */
    protected $dispatchesEvents = [
        'created' => \App\Events\Appointment\AppointmentCreated::class,
    ];

    // ═══════════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * The customer who booked (nullable for walk-ins).
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

        $this->status = self::STATUS_CONFIRMED;
        return $this->save();
    }

    /**
     * Cancel this appointment.
     */
    public function cancel(): bool
    {
        if (in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_COMPLETED])) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Mark this appointment as completed.
     */
    public function complete(): bool
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;
        return $this->save();
    }

    /**
     * Check if appointment can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }
}
