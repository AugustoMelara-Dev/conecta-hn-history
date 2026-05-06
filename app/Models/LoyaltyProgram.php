<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Loyalty Program Model - Tenant Configuration
 * 
 * Defines the loyalty program settings for a tenant:
 * - Earn ratio (how much to spend per point)
 * - Redemption ratio (how many points per currency unit)
 * - Expiry policy
 * 
 * INVARIANT: One program per tenant (unique constraint)
 */
class LoyaltyProgram extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'earn_ratio',
        'redemption_ratio',
        'expiry_days',
        'is_active',
    ];

    protected $casts = [
        'earn_ratio' => 'integer',
        'redemption_ratio' => 'integer',
        'expiry_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns this program.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Calculate points for a given order total.
     * 
     * @param float|string $orderTotal The order total in currency
     * @return int Points to award (always integer, floored)
     */
    public function calculatePoints(float|string $orderTotal): int
    {
        if (!$this->is_active || $this->earn_ratio <= 0) {
            return 0;
        }

        return (int) floor((float) $orderTotal / $this->earn_ratio);
    }

    /**
     * Calculate currency value for given points.
     * 
     * @param int $points Points to convert
     * @return float Currency value
     */
    public function calculateRedemptionValue(int $points): float
    {
        if ($this->redemption_ratio <= 0) {
            return 0.0;
        }

        return round($points / $this->redemption_ratio, 2);
    }
}
