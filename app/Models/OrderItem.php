<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrderItem Model - Line Item with Price Snapshot
 *
 * Represents a single product in an order.
 * Stores frozen price/name data to preserve historical accuracy.
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'unit_price',
        'quantity',
        'line_total',
        'options',
        'options_total',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'options_total' => 'decimal:2',
        'quantity' => 'integer',
        'options' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════════════════

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════════════════

    public function getFormattedUnitPriceAttribute(): string
    {
        return 'L. '.number_format($this->unit_price, 2);
    }

    public function getFormattedLineTotalAttribute(): string
    {
        return 'L. '.number_format($this->line_total, 2);
    }

    public function getOptionsDescriptionAttribute(): string
    {
        if (! $this->options || empty($this->options)) {
            return '';
        }

        return collect($this->options)
            ->map(fn ($opt) => $opt['option'] ?? '')
            ->filter()
            ->implode(', ');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FACTORY METHOD
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create an OrderItem from a cart item payload.
     *
     * @param  array  $cartItem  Cart item from frontend
     */
    public static function fromCartItem(array $cartItem): static
    {
        $unitPrice = (float) ($cartItem['price'] ?? 0);
        $quantity = (int) ($cartItem['quantity'] ?? 1);
        $options = $cartItem['options'] ?? [];
        $optionsTotal = collect($options)->sum('price');
        $lineTotal = ($unitPrice + $optionsTotal) * $quantity;

        return new static([
            'product_id' => $cartItem['product_id'] ?? $cartItem['id'] ?? null,
            'product_name' => $cartItem['name'] ?? 'Producto',
            'product_sku' => $cartItem['sku'] ?? null,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'options' => $options,
            'options_total' => $optionsTotal,
            'line_total' => $lineTotal,
            'notes' => $cartItem['notes'] ?? null,
        ]);
    }
}
