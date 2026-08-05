<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Enums;

/**
 * Capability - Canonical feature vocabulary for tenant-level behavior.
 *
 * Controllers, jobs, UI, and policies should ask for capabilities instead of
 * branching directly on business_type.
 */
enum Capability: string
{
    case BILLING_PORTAL = 'billing_portal';
    case CATALOG_BASIC = 'catalog_basic';
    case CHECKOUT_BASIC = 'checkout_basic';
    case CUSTOMER_DIRECTORY = 'customer_directory';
    case CRM_PIPELINE_MANAGEMENT = 'crm_pipeline_management';
    case PAYMENTS_BASIC = 'payments_basic';
    case NOTIFICATIONS_BASIC = 'notifications_basic';
    case ANALYTICS_BASIC = 'analytics_basic';
    case COUPONS_BASIC = 'coupons_basic';
    case STAFF_BASIC = 'staff_basic';
    case AI_CONTENT = 'ai_content';
    case WEB_PUSH = 'web_push';

    case PRODUCT_VARIANTS = 'product_variants';
    case INVENTORY_TRACKING = 'inventory_tracking';
    case STOCK_ALERTS = 'stock_alerts';
    case PICKUP_ORDERS = 'pickup_orders';
    case SHIPPING_ORDERS = 'shipping_orders';
    case DELIVERY_ORDERS = 'delivery_orders';

    case MENU_MODIFIERS = 'menu_modifiers';
    case KITCHEN_DISPLAY = 'kitchen_display';
    case TABLE_MANAGEMENT = 'table_management';

    case APPOINTMENT_BOOKING = 'appointment_booking';
    case STAFF_CALENDAR = 'staff_calendar';
    case SERVICE_DURATION_BUFFERS = 'service_duration_buffers';
    case SERVICE_PACKAGES = 'service_packages';
    case APPOINTMENT_REMINDERS = 'appointment_reminders';
}
