<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

/**
 * PaymentStatus - Finite State Machine for Payments
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TYPE SAFETY: Eliminates "magic strings" for status state.               ║
 * ║  BACKED ENUM: Maps to string values in database.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case REJECTED = 'rejected';
    case REVIEW = 'review';
}
