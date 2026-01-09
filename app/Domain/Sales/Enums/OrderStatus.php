<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * OrderStatus - Domain State Definition
 * 
 * Maps to App\Models\Order constants.
 * Exposed to Frontend for type-safe Status Badges.
 */
#[TypeScript]
enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}
