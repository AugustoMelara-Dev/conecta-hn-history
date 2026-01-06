<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * PaymentMethod - Type-Safe Payment Method Values
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SINGLE SOURCE OF TRUTH: All payment method logic references this Enum ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * MARKET CONTEXT (Honduras):
 * - Low credit card penetration
 * - High reliance on bank transfers & cash
 * - Manual verification workflows required
 *
 * USAGE:
 * $order->payment_method = PaymentMethod::BankTransfer;
 * if ($order->payment_method === PaymentMethod::Cash) { ... }
 *
 * ELOQUENT CAST (in Order model):
 * protected $casts = [
 *     'payment_method' => PaymentMethod::class,
 * ];
 */
enum PaymentMethod: string
{
    /**
     * Cash on Delivery - Physical payment at delivery time.
     * Timeout: 24 hours (tied to logistics cycles)
     */
    case Cash = 'cash';

    /**
     * Bank Transfer - Manual transfer requiring verification.
     * Timeout: 4 hours (human latency + verification)
     */
    case BankTransfer = 'bank_transfer';

    /**
     * Credit/Debit Card - Automated digital payment.
     * Timeout: 30 minutes (abandoned cart detection)
     */
    case Card = 'card';

    /**
     * Pending - Payment method not yet selected.
     * Used during checkout flow before final selection.
     */
    case Pending = 'pending';

    /**
     * Get human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::BankTransfer => 'Transferencia Bancaria',
            self::Card => 'Tarjeta',
            self::Pending => 'Pendiente',
        };
    }

    /**
     * Get the stale order timeout in minutes.
     *
     * BUSINESS LOGIC:
     * - Bank transfers need extended time for manual verification
     * - Cash orders are tied to delivery logistics (24h cycle)
     * - Card/digital payments use strict timeout for abandoned carts
     */
    public function staleTimeoutMinutes(): int
    {
        return match ($this) {
            self::Cash => 24 * 60,        // 24 hours
            self::BankTransfer => 4 * 60, // 4 hours
            self::Card => 30,             // 30 minutes
            self::Pending => 30,          // 30 minutes (default)
        };
    }

    /**
     * Check if this payment method requires manual verification.
     */
    public function requiresManualVerification(): bool
    {
        return match ($this) {
            self::BankTransfer => true,
            self::Cash, self::Card, self::Pending => false,
        };
    }

    /**
     * Get all valid method values as array (for validation rules).
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the icon for UI display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Cash => '💵',
            self::BankTransfer => '🏦',
            self::Card => '💳',
            self::Pending => '⏳',
        };
    }
}
