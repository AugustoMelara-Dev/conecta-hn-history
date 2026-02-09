<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Loyalty Reward Model - Redeemable Catalog
 * 
 * Defines rewards that customers can redeem with their points.
 * Each reward belongs to a specific tenant.
 */
class LoyaltyReward extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'description',
        'points_cost',
        'stock',
        'is_active',
    ];

    protected $casts = [
        'points_cost' => 'integer',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns this reward.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if this reward is available for redemption.
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // -1 means unlimited stock
        if ($this->stock === -1) {
            return true;
        }

        return $this->stock > 0;
    }

    /**
     * Decrement stock after redemption.
     */
    public function decrementStock(): void
    {
        if ($this->stock > 0) {
            $this->decrement('stock');
        }
    }

    /**
     * Scope to get only available rewards.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->where('stock', -1)
                           ->orWhere('stock', '>', 0);
                     });
    }
}
