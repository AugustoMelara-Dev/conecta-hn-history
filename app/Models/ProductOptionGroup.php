<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOptionGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'type',
        'is_required',
        'min_selections',
        'max_selections',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'sort_order' => 'integer',
    ];

    // ═══ RELACIONES ═══

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'option_group_id')
            ->orderBy('sort_order');
    }

    public function availableOptions(): HasMany
    {
        return $this->options()->where('is_available', true);
    }

    // ═══ HELPERS ═══

    public function isSingleSelection(): bool
    {
        return $this->type === 'single';
    }

    public function isMultipleSelection(): bool
    {
        return $this->type === 'multiple';
    }

    /**
     * Valida si una cantidad de selecciones es válida para este grupo
     */
    public function isValidSelectionCount(int $count): bool
    {
        if ($this->is_required && $count === 0) {
            return false;
        }

        if ($count < $this->min_selections) {
            return false;
        }

        if ($this->max_selections !== null && $count > $this->max_selections) {
            return false;
        }

        return true;
    }
}
