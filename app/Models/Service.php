<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service Model - Bookable Service Definition
 *
 * Represents a service that can be booked (time-based inventory).
 * Examples: "Men's Haircut - 30min - L.200", "Dental Cleaning - 60min - L.500"
 *
 * PRICING: Stored as decimal(10,2) for currency precision.
 * DURATION: Stored in minutes to avoid timezone issues.
 *
 * SECURITY: Uses BelongsToTenant for automatic tenant isolation.
 */
class Service extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'duration_minutes',
        'buffer_before',
        'buffer_after',
        'price',
        'is_active',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'buffer_before' => 'integer',
        'buffer_after' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get all appointments for this service.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Scope to only active services.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the total duration of the service including buffers.
     */
    public function getTotalDurationAttribute(): int
    {
        return $this->duration_minutes + $this->buffer_before + $this->buffer_after;
    }

    /**
     * Get formatted duration (e.g., "30 min", "1h 30min").
     */
    public function getFormattedDurationAttribute(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}min";
        } elseif ($hours > 0) {
            return "{$hours}h";
        }

        return "{$minutes} min";
    }

    /**
     * Get formatted price with currency.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'L. '.number_format((float) $this->price, 2);
    }
}
