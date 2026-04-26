<?php

declare(strict_types=1);

return [
    'enabled' => env('DYNAMIC_FORMS_ENABLED', true),

    // Global emergency brake for dynamic runtime.
    'kill_switch' => env('DYNAMIC_FORMS_KILL_SWITCH', false),

    'admin_configurator_enabled' => env('DYNAMIC_FORMS_ADMIN_ENABLED', true),

    'tenant_configurator_enabled' => env('DYNAMIC_FORMS_TENANT_ENABLED', true),

    'record_runtime_enabled' => env('DYNAMIC_FORMS_RECORD_RUNTIME_ENABLED', true),

    'print_runtime_enabled' => env('DYNAMIC_FORMS_PRINT_RUNTIME_ENABLED', true),

    'modules' => [
        'crm' => env('DYNAMIC_FORMS_MODULE_CRM', true),
        'customers' => env('DYNAMIC_FORMS_MODULE_CUSTOMERS', true),
        'appointments' => env('DYNAMIC_FORMS_MODULE_APPOINTMENTS', true),
        'orders' => env('DYNAMIC_FORMS_MODULE_ORDERS', true),
        'inventory' => env('DYNAMIC_FORMS_MODULE_INVENTORY', true),
        'operations' => env('DYNAMIC_FORMS_MODULE_OPERATIONS', true),
        'services' => env('DYNAMIC_FORMS_MODULE_SERVICES', true),
    ],

    // Rollout bridge by operational module while static forms still coexist.
    'module_adapters' => [
        'crm' => [
            'legacy_resources' => ['leads'],
            'dynamic_entities' => ['lead'],
            'migration_enabled' => env('DYNAMIC_FORMS_ADAPTER_CRM', true),
        ],
        'customers' => [
            'legacy_resources' => ['customer_profiles'],
            'dynamic_entities' => ['customer'],
            'migration_enabled' => env('DYNAMIC_FORMS_ADAPTER_CUSTOMERS', true),
        ],
        'appointments' => [
            'legacy_resources' => ['appointments'],
            'dynamic_entities' => ['appointment'],
            'migration_enabled' => env('DYNAMIC_FORMS_ADAPTER_APPOINTMENTS', true),
        ],
        'orders' => [
            'legacy_resources' => ['orders'],
            'dynamic_entities' => ['order'],
            'migration_enabled' => env('DYNAMIC_FORMS_ADAPTER_ORDERS', true),
        ],
        'inventory' => [
            'legacy_resources' => ['products', 'inventory_levels', 'inventory_movements'],
            'dynamic_entities' => ['inventory_item', 'inventory_movement'],
            'migration_enabled' => env('DYNAMIC_FORMS_ADAPTER_INVENTORY', true),
        ],
    ],
];
