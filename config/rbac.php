<?php

declare(strict_types=1);

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\TenantGovernance\Enums\Capability;

return [
    'roles' => [
        Role::OWNER->value => array_map(
            static fn (Permission $permission): string => $permission->value,
            Permission::cases()
        ),

        Role::ADMIN->value => [
            Permission::TENANT_VIEW->value,
            Permission::TENANT_UPDATE->value,
            Permission::SETTINGS_VIEW->value,
            Permission::SETTINGS_UPDATE->value,
            Permission::BILLING_VIEW->value,
            Permission::CATALOG_VIEW->value,
            Permission::CATALOG_CREATE->value,
            Permission::CATALOG_UPDATE->value,
            Permission::CATALOG_DELETE->value,
            Permission::CATALOG_PUBLISH->value,
            Permission::MENU_VIEW->value,
            Permission::MENU_MANAGE->value,
            Permission::INVENTORY_VIEW->value,
            Permission::INVENTORY_ADJUST->value,
            Permission::INVENTORY_TRANSFER->value,
            Permission::ORDERS_VIEW->value,
            Permission::ORDERS_CREATE->value,
            Permission::ORDERS_MANAGE->value,
            Permission::ORDERS_FULFILL->value,
            Permission::ORDERS_CANCEL->value,
            Permission::ORDERS_EXPORT->value,
            Permission::PAYMENTS_VIEW->value,
            Permission::PAYMENTS_RECORD->value,
            Permission::CUSTOMERS_VIEW->value,
            Permission::CUSTOMERS_EXPORT->value,
            Permission::APPOINTMENTS_VIEW->value,
            Permission::APPOINTMENTS_MANAGE->value,
            Permission::STAFF_VIEW->value,
            Permission::STAFF_MANAGE->value,
            Permission::MARKETING_VIEW->value,
            Permission::MARKETING_MANAGE->value,
            Permission::ANALYTICS_VIEW->value,
            Permission::ANALYTICS_EXPORT->value,
            Permission::INTEGRATIONS_VIEW->value,
            Permission::WEBHOOKS_VIEW->value,
            Permission::AI_USE->value,
        ],

        Role::MANAGER->value => [
            Permission::TENANT_VIEW->value,
            Permission::SETTINGS_VIEW->value,
            Permission::CATALOG_VIEW->value,
            Permission::CATALOG_CREATE->value,
            Permission::CATALOG_UPDATE->value,
            Permission::CATALOG_PUBLISH->value,
            Permission::MENU_VIEW->value,
            Permission::MENU_MANAGE->value,
            Permission::INVENTORY_VIEW->value,
            Permission::INVENTORY_ADJUST->value,
            Permission::ORDERS_VIEW->value,
            Permission::ORDERS_CREATE->value,
            Permission::ORDERS_MANAGE->value,
            Permission::ORDERS_FULFILL->value,
            Permission::ORDERS_CANCEL->value,
            Permission::ORDERS_EXPORT->value,
            Permission::PAYMENTS_VIEW->value,
            Permission::PAYMENTS_RECORD->value,
            Permission::CUSTOMERS_VIEW->value,
            Permission::APPOINTMENTS_VIEW->value,
            Permission::APPOINTMENTS_MANAGE->value,
            Permission::STAFF_VIEW->value,
            Permission::MARKETING_VIEW->value,
            Permission::MARKETING_MANAGE->value,
            Permission::ANALYTICS_VIEW->value,
            Permission::AI_USE->value,
        ],

        Role::OPERATOR->value => [
            Permission::CATALOG_VIEW->value,
            Permission::MENU_VIEW->value,
            Permission::INVENTORY_VIEW->value,
            Permission::ORDERS_VIEW->value,
            Permission::ORDERS_FULFILL->value,
            Permission::PAYMENTS_VIEW->value,
            Permission::CUSTOMERS_VIEW->value,
            Permission::APPOINTMENTS_VIEW->value,
            Permission::AI_USE->value,
        ],

        Role::VIEWER->value => [
            Permission::TENANT_VIEW->value,
            Permission::SETTINGS_VIEW->value,
            Permission::CATALOG_VIEW->value,
            Permission::MENU_VIEW->value,
            Permission::ORDERS_VIEW->value,
            Permission::PAYMENTS_VIEW->value,
            Permission::CUSTOMERS_VIEW->value,
            Permission::APPOINTMENTS_VIEW->value,
            Permission::STAFF_VIEW->value,
            Permission::MARKETING_VIEW->value,
            Permission::ANALYTICS_VIEW->value,
            Permission::INTEGRATIONS_VIEW->value,
            Permission::WEBHOOKS_VIEW->value,
        ],
    ],

    'presets' => [
        'cashier' => [
            'role' => Role::OPERATOR->value,
            'permissions' => [
                Permission::ORDERS_VIEW->value,
                Permission::ORDERS_MANAGE->value,
                Permission::PAYMENTS_VIEW->value,
                Permission::PAYMENTS_RECORD->value,
                Permission::CUSTOMERS_VIEW->value,
            ],
            'requires_capability' => Capability::CHECKOUT_BASIC->value,
        ],

        'kitchen_staff' => [
            'role' => Role::OPERATOR->value,
            'permissions' => [
                Permission::ORDERS_VIEW->value,
                Permission::ORDERS_FULFILL->value,
            ],
            'requires_capability' => Capability::KITCHEN_DISPLAY->value,
        ],

        'service_provider' => [
            'role' => Role::OPERATOR->value,
            'permissions' => [
                Permission::APPOINTMENTS_VIEW->value,
                Permission::APPOINTMENTS_MANAGE->value,
                Permission::CUSTOMERS_VIEW->value,
            ],
            'requires_capability' => Capability::APPOINTMENT_BOOKING->value,
        ],

        'warehouse_clerk' => [
            'role' => Role::OPERATOR->value,
            'permissions' => [
                Permission::INVENTORY_VIEW->value,
                Permission::INVENTORY_ADJUST->value,
                Permission::INVENTORY_TRANSFER->value,
            ],
            'requires_capability' => Capability::INVENTORY_TRACKING->value,
        ],
    ],
];
