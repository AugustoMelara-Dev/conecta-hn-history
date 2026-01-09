<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Marketing;

/**
 * CouponType - Enum for coupon discount calculation methods.
 */
enum CouponType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED_AMOUNT = 'fixed';
    case FREE_SHIPPING = 'free_shipping';

    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Porcentaje',
            self::FIXED_AMOUNT => 'Monto Fijo',
            self::FREE_SHIPPING => 'Envío Gratis',
        };
    }
}
