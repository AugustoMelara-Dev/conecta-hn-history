<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

enum CommercialOutcome: string
{
    case OrderPaid = 'order_paid';
    case OrderRefunded = 'order_refunded';
    case OrderDelivered = 'order_delivered';
    case OrderCancelled = 'order_cancelled';
    case AppointmentConfirmed = 'appointment_confirmed';
    case AppointmentCompleted = 'appointment_completed';
    case AppointmentCancelled = 'appointment_cancelled';

    public function carriesRevenue(): bool
    {
        return in_array($this, [self::OrderPaid, self::OrderRefunded], true);
    }
}
