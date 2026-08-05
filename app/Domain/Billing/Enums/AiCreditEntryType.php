<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum AiCreditEntryType: string
{
    case MONTHLY_GRANT = 'monthly_grant';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';
    case RESERVATION_CREATED = 'reservation_created';
    case RESERVATION_SETTLED = 'reservation_settled';
    case RESERVATION_REFUNDED = 'reservation_refunded';
}
