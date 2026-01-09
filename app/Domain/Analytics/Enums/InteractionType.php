<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

/**
 * InteractionType - Domain Enum for Conversion Event Types
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: Domain Layer Enum                             ║
 * ║  - Decouples validation logic from Eloquent Model                        ║
 * ║  - Single source of truth for valid interaction types                    ║
 * ║  - Enables type-safe usage across Application and Presentation layers   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
enum InteractionType: string
{
    case WHATSAPP_CLICK = 'whatsapp_click';
    case CALL = 'call';
    case LOCATION_VIEW = 'location_view';
    case CART_ADD = 'cart_add';
    case SOCIAL_SHARE = 'social_share';
    case CONVERSION = 'conversion';
    case PURCHASE = 'purchase';

    /**
     * Get all valid type values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get human-readable labels for display.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::WHATSAPP_CLICK->value => 'WhatsApp Click',
            self::CALL->value => 'Phone Call',
            self::LOCATION_VIEW->value => 'Location View',
            self::CART_ADD->value => 'Add to Cart',
            self::SOCIAL_SHARE->value => 'Social Share',
            self::CONVERSION->value => 'Conversion',
            self::PURCHASE->value => 'Purchase',
        ];
    }
}
