<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;

return [
    'always' => [
        Capability::CATALOG_BASIC->value,
        Capability::CHECKOUT_BASIC->value,
        Capability::CUSTOMER_DIRECTORY->value,
        Capability::PAYMENTS_BASIC->value,
        Capability::NOTIFICATIONS_BASIC->value,
        Capability::ANALYTICS_BASIC->value,
        Capability::COUPONS_BASIC->value,
        Capability::STAFF_BASIC->value,
        Capability::AI_CONTENT->value,
        Capability::WEB_PUSH->value,
    ],

    'business_types' => [
        BusinessType::RETAIL->value => [
            Capability::PRODUCT_VARIANTS->value,
            Capability::INVENTORY_TRACKING->value,
            Capability::STOCK_ALERTS->value,
            Capability::PICKUP_ORDERS->value,
            Capability::SHIPPING_ORDERS->value,
        ],

        BusinessType::FOOD->value => [
            Capability::MENU_MODIFIERS->value,
            Capability::KITCHEN_DISPLAY->value,
            Capability::TABLE_MANAGEMENT->value,
            Capability::DELIVERY_ORDERS->value,
            Capability::PICKUP_ORDERS->value,
        ],

        BusinessType::SERVICE->value => [
            Capability::APPOINTMENT_BOOKING->value,
            Capability::STAFF_CALENDAR->value,
            Capability::SERVICE_DURATION_BUFFERS->value,
            Capability::SERVICE_PACKAGES->value,
            Capability::APPOINTMENT_REMINDERS->value,
        ],

        BusinessType::HYBRID->value => [
            Capability::PRODUCT_VARIANTS->value,
            Capability::INVENTORY_TRACKING->value,
            Capability::PICKUP_ORDERS->value,
            Capability::DELIVERY_ORDERS->value,
            Capability::APPOINTMENT_BOOKING->value,
            Capability::STAFF_CALENDAR->value,
            Capability::SERVICE_PACKAGES->value,
        ],
    ],
];
