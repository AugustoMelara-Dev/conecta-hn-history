<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

/**
 * OrderStatus - Domain State Definition
 *
 * Maps to App\Models\Order constants.
 * Exposed to Frontend for type-safe Status Badges.
 *
 * NOTE (A5): #[TypeScript] attribute removed to maintain Domain purity.
 * TypeScript type generation should be configured manually via
 * config/typescript-transformer.php or declared directly in the frontend.
 */
enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}
