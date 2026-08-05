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
    case MICROSITE_VIEW = 'microsite_view';
    case CHECKOUT_STARTED = 'checkout_started';
    case CHECKOUT_PAID = 'checkout_paid';
    case UPGRADE_STARTED = 'upgrade_started';
    case UPGRADE_PAID = 'upgrade_paid';
    case LEAD_CREATED = 'lead_created';

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
     * Signals a public client is allowed to report. These are intent/traffic
     * observations only; none of them grants revenue or conversion authority.
     *
     * @return list<string>
     */
    public static function publicSignalValues(): array
    {
        return [
            self::WHATSAPP_CLICK->value,
            self::CALL->value,
            self::LOCATION_VIEW->value,
            self::CART_ADD->value,
            self::SOCIAL_SHARE->value,
            self::MICROSITE_VIEW->value,
            self::CHECKOUT_STARTED->value,
            self::UPGRADE_STARTED->value,
        ];
    }

    public function isServerAuthoritative(): bool
    {
        return in_array($this, [
            self::CONVERSION,
            self::PURCHASE,
            self::CHECKOUT_PAID,
            self::UPGRADE_PAID,
            self::LEAD_CREATED,
        ], true);
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
            self::MICROSITE_VIEW->value => 'Microsite Visit',
            self::CHECKOUT_STARTED->value => 'Checkout Started',
            self::CHECKOUT_PAID->value => 'Checkout Paid',
            self::UPGRADE_STARTED->value => 'Upgrade Started',
            self::UPGRADE_PAID->value => 'Upgrade Paid',
            self::LEAD_CREATED->value => 'Lead Created',
        ];
    }
}
