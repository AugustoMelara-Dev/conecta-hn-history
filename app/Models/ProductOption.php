<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'option_group_id',
        'name',
        'price_modifier',
        'is_default',
        'is_available',
        'sort_order',
    ];

    protected $casts = [
        'price_modifier' => 'decimal:2',
        'is_default' => 'boolean',
        'is_available' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ═══ RELACIONES ═══

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'option_group_id');
    }

    // ═══ HELPERS ═══

    /**
     * Obtiene el producto asociado a través del grupo
     */
    public function getProduct(): ?Product
    {
        return $this->optionGroup?->product;
    }

    /**
     * Formatea el modificador de precio para mostrar
     */
    public function getFormattedPriceModifier(): string
    {
        if ($this->price_modifier == 0) {
            return '';
        }

        $sign = $this->price_modifier > 0 ? '+' : '';

        return sprintf('%sL. %.2f', $sign, $this->price_modifier);
    }

    /**
     * Retorna datos para snapshot en order_items
     */
    public function toOrderSnapshot(): array
    {
        return [
            'group' => $this->optionGroup?->name,
            'option' => $this->name,
            'modifier' => $this->price_modifier,
        ];
    }
}
