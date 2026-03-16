<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrderItem Model - Line Item with Price Snapshot
 *
 * Represents a single product in an order.
 * Stores frozen price/name data to preserve historical accuracy.
 *
 * ALIGNED WITH: 2025_01_02_000002_create_order_items_table.php
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_price', // Snapshot price at order time
        'quantity',
        'unit_price',    // Price with modifiers applied
        'total_price',   // unit_price * quantity
        'selected_options', // JSON with variant/addon selections
        'line_total',
        'options',
        'notes',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'quantity' => 'integer',
        'selected_options' => 'array',
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

    public function getFormattedTotalPriceAttribute(): string
    {
        return 'L. '.number_format($this->total_price, 2);
    }

    public function getOptionsDescriptionAttribute(): string
    {
        if (! $this->selected_options || empty($this->selected_options)) {
            return '';
        }

        return collect($this->selected_options)
            ->map(fn ($opt) => $opt['option'] ?? '')
            ->filter()
            ->implode(', ');
    }

    public function getOptionsAttribute($value): array
    {
        if ($value !== null) {
            return is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
        }

        return $this->selected_options ?? [];
    }

    public function getLineTotalAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        return (float) ($this->total_price ?? 0);
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
        $productPrice = (float) ($cartItem['price'] ?? 0);
        $quantity = (int) ($cartItem['quantity'] ?? 1);
        $options = $cartItem['options'] ?? [];
        $optionsTotal = collect($options)->sum('price');
        $unitPrice = $productPrice + $optionsTotal;
        $totalPrice = $unitPrice * $quantity;

        return new static([
            'product_id' => $cartItem['product_id'] ?? $cartItem['id'] ?? null,
            'product_name' => $cartItem['name'] ?? 'Producto',
            'product_price' => $productPrice,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'selected_options' => $options,
            'total_price' => $totalPrice,
            'notes' => $cartItem['notes'] ?? null,
        ]);
    }
}
