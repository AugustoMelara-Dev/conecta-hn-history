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

    // ═══════════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Get all appointments for this provider.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class, 'service_provider_id');
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class, 'service_provider_id');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Scope to only active providers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // BUSINESS LOGIC
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Check if provider is available at a specific time slot.
     */
    /**
     * @deprecated Compatibility helper only. Operational booking authority is AvailabilityQuery.
     */
    public function isAvailableAt(\DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        return ! $this->appointments()
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start) {
                    // New appointment starts during existing
                    $q->where('scheduled_at', '<=', $start)
                        ->where('end_at', '>', $start);
                })->orWhere(function ($q) use ($end) {
                    // New appointment ends during existing
                    $q->where('scheduled_at', '<', $end)
                        ->where('end_at', '>=', $end);
                })->orWhere(function ($q) use ($start, $end) {
                    // New appointment contains existing
                    $q->where('scheduled_at', '>=', $start)
                        ->where('end_at', '<=', $end);
                });
            })
            ->exists();
    }

    /**
     * Get opening hours for this provider.
     *
     * HIERARCHY: Provider schedule_config > Tenant opening hours > Default 9-5
     */
    /**
     * @deprecated Legacy shadow reader only. Operational booking authority is AvailabilityQuery.
     */
    public function getOpeningHours(): OpeningHours
    {
        // Provider-specific schedule takes precedence
        if (! empty($this->schedule_config)) {
            return OpeningHours::create($this->schedule_config);
        }

        // Fall back to tenant's opening hours
        return $this->tenant->getOpeningHours();
    }
}
