<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\OpeningHours\OpeningHours;

/**
 * ServiceProvider Model - Staff/Resource Definition
 *
 * Represents a person or resource that can perform services.
 * Each provider has their own schedule configuration that can
 * override tenant-level opening hours via spatie/opening-hours.
 *
 * SECURITY: Uses BelongsToTenant for automatic tenant isolation.
 */
class ServiceProvider extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'schedule_config',
        'is_active',
    ];

    protected $casts = [
        'schedule_config' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get all appointments for this provider.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Scope to only active providers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if provider is available at a specific time slot, accounting for buffers.
     *
     * @param  \DateTimeInterface  $start  The requested start time.
     * @param  \DateTimeInterface  $end  The requested end time.
     * @param  int  $bufferBefore  Minutes required before the appointment.
     * @param  int  $bufferAfter  Minutes required after the appointment.
     */
    public function isAvailableAt(\DateTimeInterface $start, \DateTimeInterface $end, int $bufferBefore = 0, int $bufferAfter = 0): bool
    {
        $paddedStart = (clone $start)->modify("-{$bufferBefore} minutes");
        $paddedEnd = (clone $end)->modify("+{$bufferAfter} minutes");

        return ! $this->appointments()
            ->where('status', '!=', 'cancelled')
            ->where('scheduled_at', '<', $paddedEnd)
            ->where('end_at', '>', $paddedStart)
            ->exists();
    }

    /**
     * Get opening hours for this provider.
     *
     * HIERARCHY: Provider schedule_config > Tenant opening hours > Default 9-5
     */
    public function getOpeningHours(): OpeningHours
    {
        if (! empty($this->schedule_config)) {
            return OpeningHours::create($this->schedule_config);
        }

        return $this->tenant->getOpeningHours();
    }
}
