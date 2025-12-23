<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ModifierOption - Individual Customization Choice
 *
 * Represents a single option within a ModifierGroup.
 * Examples: "Grande" (+L.50), "Pepperoni" (+L.25), "Sin cebolla" (+L.0)
 *
 * PRICING:
 * - price_adjustment: Amount added/subtracted from product base price
 * - Positive = surcharge, Negative = discount, Zero = no change
 */
class ModifierOption extends Model
{
    protected $fillable = [
        'modifier_group_id',
        'name',
        'price_adjustment',
        'is_available',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_available' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the modifier group that owns this option.
     */
    public function modifierGroup(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class);
    }

    /**
     * Scope: Only available options.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Get formatted price adjustment for display.
     * Returns "+L.50" or "-L.10" or empty string if zero.
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price_adjustment == 0) {
            return '';
        }

        $sign = $this->price_adjustment > 0 ? '+' : '';

        return $sign.'L.'.number_format(abs($this->price_adjustment), 2);
    }
}
