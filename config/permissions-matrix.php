<?php

declare(strict_types=1);

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\TenantGovernance\Enums\Capability;

return [
    'modules' => [
        'sales' => [
            'capability' => Capability::CHECKOUT_BASIC->value,
            'role_permissions' => [
                Role::OWNER->value => Permission::ORDERS_MANAGE->value,
                Role::ADMIN->value => Permission::ORDERS_MANAGE->value,
                Role::MANAGER->value => Permission::ORDERS_MANAGE->value,
                Role::OPERATOR->value => Permission::ORDERS_FULFILL->value,
                Role::VIEWER->value => Permission::ORDERS_VIEW->value,
            ],
        ],
        'agenda' => [
            'capability' => Capability::APPOINTMENT_BOOKING->value,
            'role_permissions' => [
                Role::OWNER->value => Permission::APPOINTMENTS_MANAGE->value,
                Role::ADMIN->value => Permission::APPOINTMENTS_MANAGE->value,
                Role::MANAGER->value => Permission::APPOINTMENTS_MANAGE->value,
                Role::OPERATOR->value => Permission::APPOINTMENTS_VIEW->value,
                Role::VIEWER->value => Permission::APPOINTMENTS_VIEW->value,
            ],
        ],
        'customers' => [
            'capability' => Capability::CUSTOMER_DIRECTORY->value,
            'role_permissions' => [
                Role::OWNER->value => Permission::CUSTOMERS_EXPORT->value,
                Role::ADMIN->value => Permission::CUSTOMERS_EXPORT->value,
                Role::MANAGER->value => Permission::CUSTOMERS_VIEW->value,
                Role::OPERATOR->value => Permission::CUSTOMERS_VIEW->value,
                Role::VIEWER->value => Permission::CUSTOMERS_VIEW->value,
            ],
        ],
        'marketing' => [
            'capability' => Capability::NOTIFICATIONS_BASIC->value,
            'role_permissions' => [
                Role::OWNER->value => Permission::MARKETING_MANAGE->value,
                Role::ADMIN->value => Permission::MARKETING_MANAGE->value,
                Role::MANAGER->value => Permission::MARKETING_MANAGE->value,
                Role::OPERATOR->value => Permission::PLAYBOOKS_VIEW->value,
                Role::VIEWER->value => Permission::MARKETING_VIEW->value,
            ],
        ],
        'finance' => [
            'capability' => Capability::PAYMENTS_BASIC->value,
            'role_permissions' => [
                Role::OWNER->value => Permission::PAYMENTS_RECORD->value,
                Role::ADMIN->value => Permission::PAYMENTS_RECORD->value,
                Role::MANAGER->value => Permission::PAYMENTS_RECORD->value,
                Role::OPERATOR->value => Permission::PAYMENTS_VIEW->value,
                Role::VIEWER->value => Permission::PAYMENTS_VIEW->value,
            ],
        ],
        'configuration' => [
            'capability' => Capability::CATALOG_BASIC->value,
            'role_permissions' => [
                Role::OWNER->value => Permission::SETTINGS_UPDATE->value,
                Role::ADMIN->value => Permission::SETTINGS_UPDATE->value,
                Role::MANAGER->value => Permission::SETTINGS_VIEW->value,
                Role::OPERATOR->value => Permission::CATALOG_VIEW->value,
                Role::VIEWER->value => Permission::SETTINGS_VIEW->value,
            ],
        ],
        'administration' => [
            'capability' => Capability::STAFF_BASIC->value,
            'role_permissions' => [
                Role::OWNER->value => Permission::STAFF_ASSIGN_ROLES->value,
                Role::ADMIN->value => Permission::STAFF_ASSIGN_ROLES->value,
                Role::MANAGER->value => Permission::STAFF_VIEW->value,
                Role::OPERATOR->value => Permission::PLAYBOOKS_VIEW->value,
                Role::VIEWER->value => Permission::STAFF_VIEW->value,
            ],
        ],
    ],
];
