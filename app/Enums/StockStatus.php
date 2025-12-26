<?php

namespace App\Enums;

/**
 * StockStatus - Type-Safe Inventory Status Values
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ENUM SAFETY: Prevents typos and invalid status values at compile time  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * BENEFITS:
 * - IDE autocompletion
 * - Type safety (no more string typos)
 * - Exhaustive switch statements
 * - Self-documenting code
 *
 * USAGE:
 * $product->stock_status = StockStatus::InStock;
 * $product->stock_status = StockStatus::OutOfStock->value; // For raw string
 *
 * ELOQUENT CAST (in Product model):
 * protected $casts = [
 *     'stock_status' => StockStatus::class,
 * ];
 */
enum StockStatus: string
{
    /**
     * Product is available for purchase.
     */
    case InStock = 'in_stock';

    /**
     * Product has zero stock and backorders disabled.
     */
    case OutOfStock = 'out_of_stock';

    /**
     * Product has zero stock but backorders are allowed.
     */
    case OnBackorder = 'on_backorder';

    /**
     * Product is temporarily unavailable (manual override).
     */
    case Discontinued = 'discontinued';

    /**
     * Get human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::InStock => 'En Stock',
            self::OutOfStock => 'Agotado',
            self::OnBackorder => 'Pedido Pendiente',
            self::Discontinued => 'Descontinuado',
        };
    }

    /**
     * Check if product is purchasable.
     */
    public function isPurchasable(): bool
    {
        return match ($this) {
            self::InStock, self::OnBackorder => true,
            self::OutOfStock, self::Discontinued => false,
        };
    }

    /**
     * Get all valid status values as array (for validation rules).
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
