<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ModifierGroup - Product Customization Categories
 * 
 * Represents a group of options for customizing a product.
 * Examples: "Tamaño", "Extras", "Término de cocción"
 * 
 * BUSINESS RULES:
 * - is_required: Customer MUST select at least min_selections
 * - min_selections: Minimum options required (0 = optional)
 * - max_selections: Maximum options allowed (1 = single choice, N = multi-select)
 */
class ModifierGroup extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'description',
        'is_required',
        'min_selections',
        'max_selections',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the product that owns this modifier group.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get all options in this group.
     */
    public function options(): HasMany
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort_order');
    }

    /**
     * Get only available options.
     */
    public function availableOptions(): HasMany
    {
        return $this->hasMany(ModifierOption::class)
            ->where('is_available', true)
            ->orderBy('sort_order');
    }

    /**
     * Scope: Only active groups.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this is a single-select group (radio buttons).
     */
    public function isSingleSelect(): bool
    {
        return $this->max_selections === 1;
    }

    /**
     * Check if this is a multi-select group (checkboxes).
     */
    public function isMultiSelect(): bool
    {
        return $this->max_selections > 1;
    }
}
