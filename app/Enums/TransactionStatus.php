<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumEnhancements;

/**
 * TransactionStatus - Rich Domain Enum for Payment States
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL: Payment State Machine                                        ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  STATE TRANSITIONS:                                                     ║
 * ║    PENDING ──► COMPLETED (successful charge)                            ║
 * ║    PENDING ──► FAILED (declined/error)                                  ║
 * ║    PENDING ──► REQUIRES_ACTION (3DS/SCA)                                ║
 * ║    REQUIRES_ACTION ──► COMPLETED | FAILED                               ║
 * ║    COMPLETED ──► REFUNDED | PARTIALLY_REFUNDED                          ║
 * ║    COMPLETED ──► DISPUTED                                               ║
 * ║                                                                          ║
 * ║  ENCAPSULATED LOGIC:                                                    ║
 * ║    isFinal(): Should polling job stop?                                  ║
 * ║    isSuccessful(): Should we fulfill order?                             ║
 * ║    canRefund(): Is refund allowed?                                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
enum TransactionStatus: string
{
    use EnumEnhancements;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case REQUIRES_ACTION = 'requires_action';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case DISPUTED = 'disputed';
    case CANCELLED = 'cancelled';

    // ═══════════════════════════════════════════════════════════════════════
    // PRESENTATION METHODS (UI)
    // ═══════════════════════════════════════════════════════════════════════

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PROCESSING => 'Procesando',
            self::REQUIRES_ACTION => 'Requiere Acción',
            self::COMPLETED => 'Completado',
            self::FAILED => 'Fallido',
            self::REFUNDED => 'Reembolsado',
            self::PARTIALLY_REFUNDED => 'Reembolso Parcial',
            self::DISPUTED => 'En Disputa',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::COMPLETED => 'success',
            self::PENDING, self::PROCESSING => 'warning',
            self::REQUIRES_ACTION => 'info',
            self::FAILED, self::CANCELLED => 'danger',
            self::REFUNDED, self::PARTIALLY_REFUNDED => 'gray',
            self::DISPUTED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::PROCESSING => 'heroicon-o-arrow-path',
            self::REQUIRES_ACTION => 'heroicon-o-finger-print',
            self::COMPLETED => 'heroicon-o-check-circle',
            self::FAILED => 'heroicon-o-x-circle',
            self::REFUNDED => 'heroicon-o-arrow-uturn-left',
            self::PARTIALLY_REFUNDED => 'heroicon-o-arrow-uturn-left',
            self::DISPUTED => 'heroicon-o-exclamation-triangle',
            self::CANCELLED => 'heroicon-o-no-symbol',
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BUSINESS LOGIC METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Is this a terminal state? (Stop polling/retrying)
     *
     * Used by background jobs to determine if they should continue
     * checking the payment gateway for status updates.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::REFUNDED,
            self::CANCELLED, self::DISPUTED => true,
            self::PENDING, self::PROCESSING,
            self::REQUIRES_ACTION, self::PARTIALLY_REFUNDED => false,
        };
    }

    /**
     * Was the payment successful? (Fulfill order)
     */
    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Can we issue a refund?
     */
    public function canRefund(): bool
    {
        return match ($this) {
            self::COMPLETED, self::PARTIALLY_REFUNDED => true,
            default => false,
        };
    }

    /**
     * Is the payment still in progress?
     */
    public function isPending(): bool
    {
        return match ($this) {
            self::PENDING, self::PROCESSING, self::REQUIRES_ACTION => true,
            default => false,
        };
    }

    /**
     * Does this status require customer action (3DS)?
     */
    public function requiresCustomerAction(): bool
    {
        return $this === self::REQUIRES_ACTION;
    }

    /**
     * Should we notify the customer about this status?
     */
    public function shouldNotifyCustomer(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::REFUNDED,
            self::REQUIRES_ACTION, self::DISPUTED => true,
            default => false,
        };
    }

    /**
     * Get customer-facing message.
     */
    public function customerMessage(): string
    {
        return match ($this) {
            self::PENDING => 'Tu pago está siendo procesado.',
            self::PROCESSING => 'Estamos verificando tu pago.',
            self::REQUIRES_ACTION => 'Se requiere verificación adicional.',
            self::COMPLETED => 'Pago completado exitosamente.',
            self::FAILED => 'El pago no pudo ser procesado.',
            self::REFUNDED => 'Tu pago ha sido reembolsado.',
            self::PARTIALLY_REFUNDED => 'Se ha procesado un reembolso parcial.',
            self::DISPUTED => 'Tu pago está en disputa.',
            self::CANCELLED => 'El pago fue cancelado.',
        };
    }
}
