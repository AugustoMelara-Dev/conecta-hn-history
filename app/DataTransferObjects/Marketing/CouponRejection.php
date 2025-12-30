<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Marketing;

/**
 * CouponRejection - Enum for coupon validation failure reasons.
 */
enum CouponRejection: string
{
    case NOT_FOUND = 'not_found';
    case EXPIRED = 'expired';
    case NOT_STARTED = 'not_started';
    case USAGE_LIMIT_REACHED = 'usage_limit';
    case USER_LIMIT_REACHED = 'user_limit';
    case MIN_SPEND_NOT_MET = 'min_spend';
    case ALREADY_USED = 'already_used';
    case INACTIVE = 'inactive';
    case PRODUCT_EXCLUDED = 'product_excluded';
    case CATEGORY_EXCLUDED = 'category_excluded';

    public function message(): string
    {
        return match($this) {
            self::NOT_FOUND => 'Cupón no encontrado',
            self::EXPIRED => 'Este cupón ha expirado',
            self::NOT_STARTED => 'Este cupón aún no está activo',
            self::USAGE_LIMIT_REACHED => 'Este cupón ha alcanzado su límite de usos',
            self::USER_LIMIT_REACHED => 'Ya has usado este cupón el máximo permitido',
            self::MIN_SPEND_NOT_MET => 'El mínimo de compra no ha sido alcanzado',
            self::ALREADY_USED => 'Ya has utilizado este cupón',
            self::INACTIVE => 'Este cupón está desactivado',
            self::PRODUCT_EXCLUDED => 'Este cupón no aplica a los productos seleccionados',
            self::CATEGORY_EXCLUDED => 'Este cupón no aplica a las categorías seleccionadas',
        };
    }
}
