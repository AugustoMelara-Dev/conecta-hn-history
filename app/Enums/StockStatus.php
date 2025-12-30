<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumEnhancements;

/**
 * StockStatus - Rich Domain Enum for Inventory States
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  BEHAVIORAL OBJECT: Encapsulates both state AND behavior                ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  Instead of:                                                            ║
 * ║    if ($product->status->value === 'in_stock') { $canBuy = true; }      ║
 * ║                                                                          ║
 * ║  Use:                                                                   ║
 * ║    if ($product->status->canPurchase()) { ... }                         ║
 * ║                                                                          ║
 * ║  ENCAPSULATED LOGIC:                                                    ║
 * ║    - canPurchase(): Can customer add to cart?                           ║
 * ║    - isBackorderable(): Show "ships in X days" message?                ║
 * ║    - requiresNotification(): Notify when back in stock?                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
enum StockStatus: string
{
    use EnumEnhancements;

    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case OnBackorder = 'on_backorder';
    case LowStock = 'low_stock';
    case Discontinued = 'discontinued';
    case PreOrder = 'pre_order';

    // ═══════════════════════════════════════════════════════════════════════
    // PRESENTATION METHODS (UI)
    // ═══════════════════════════════════════════════════════════════════════

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'En Stock',
            self::OutOfStock => 'Agotado',
            self::OnBackorder => 'Pedido Pendiente',
            self::LowStock => 'Pocas Unidades',
            self::Discontinued => 'Descontinuado',
            self::PreOrder => 'Pre-orden',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::LowStock => 'warning',
            self::OnBackorder, self::PreOrder => 'info',
            self::OutOfStock => 'danger',
            self::Discontinued => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::InStock => 'heroicon-o-check-circle',
            self::LowStock => 'heroicon-o-exclamation-triangle',
            self::OnBackorder => 'heroicon-o-clock',
            self::PreOrder => 'heroicon-o-calendar',
            self::OutOfStock => 'heroicon-o-x-circle',
            self::Discontinued => 'heroicon-o-archive-box-x-mark',
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BUSINESS LOGIC METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Can customer add this product to cart?
     */
    public function canPurchase(): bool
    {
        return match ($this) {
            self::InStock, self::LowStock, self::OnBackorder, self::PreOrder => true,
            self::OutOfStock, self::Discontinued => false,
        };
    }

    /**
     * Is this a backorder situation (ships later)?
     */
    public function isBackorderable(): bool
    {
        return match ($this) {
            self::OnBackorder, self::PreOrder => true,
            default => false,
        };
    }

    /**
     * Should we show "notify when available" option?
     */
    public function requiresNotification(): bool
    {
        return match ($this) {
            self::OutOfStock, self::PreOrder => true,
            default => false,
        };
    }

    /**
     * Is this product still being sold?
     */
    public function isActive(): bool
    {
        return $this !== self::Discontinued;
    }

    /**
     * Get estimated availability message.
     */
    public function availabilityMessage(): string
    {
        return match ($this) {
            self::InStock => 'Disponible para envío inmediato',
            self::LowStock => '¡Últimas unidades disponibles!',
            self::OnBackorder => 'Disponible en 7-14 días',
            self::PreOrder => 'Reserva ahora, envío próximamente',
            self::OutOfStock => 'Temporalmente agotado',
            self::Discontinued => 'Este producto ya no está disponible',
        };
    }
}
