<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $name
 * @property float|string $price_modifier
 * @property-read ProductOptionGroup|null $optionGroup
 */
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
        $group = $this->optionGroup;

        if (! $group instanceof ProductOptionGroup) {
            return null;
        }

        return $group->product;
    }

    /**
     * Formatea el modificador de precio para mostrar
     */
    public function getFormattedPriceModifier(): string
    {
        $priceModifier = (float) $this->price_modifier;

        if ($priceModifier == 0.0) {
            return '';
        }

        $sign = $priceModifier > 0 ? '+' : '';

        return sprintf('%sL. %.2f', $sign, $priceModifier);
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
