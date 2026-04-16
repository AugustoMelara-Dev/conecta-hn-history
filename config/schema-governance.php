<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed duplicate migration timestamps (historical legacy only)
    |--------------------------------------------------------------------------
    |
    | These duplicates are known legacy collisions. Any new duplicate timestamp
    | group outside this allowlist must fail db:audit-migrations.
    |
    */
    'allowed_duplicate_timestamps' => [
        [
            '2026_01_20_000009_create_outbox_sequences_table.php',
            '2026_01_20_000009_optimize_outbox_indexes.php',
        ],
        [
            '2026_03_15_000001_add_audience_type_to_campaigns_table.php',
            '2026_03_15_000001_add_subscription_extended_at_to_subscription_payments_table.php',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables with strict migration governance
    |--------------------------------------------------------------------------
    |
    | These tables have historical churn and are guarded by db:audit-migrations.
    | Duplicate Schema::create() definitions must include explicit compatibility
    | strategy guards (hasTable/dropIfExists or governance comments).
    |
    */
    'governed_tables' => [
        'orders',
        'favorites',
        'menu_sections',
        'interactions',
        'tenant_visits',
        'notifications',
        'one_time_passwords',
        'inventory_holds',
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema key-type contracts (critical tenant/user paths)
    |--------------------------------------------------------------------------
    */
    'key_type_contracts' => [
        'tenants' => [
            'id' => ['char(36)', 'uuid'],
            'user_id' => ['char(36)', 'uuid'],
        ],
        'users' => [
            'id' => ['char(36)', 'uuid'],
            'tenant_id' => ['char(36)', 'uuid'],
        ],
        'tenant_memberships' => [
            'id' => ['char(36)', 'uuid'],
            'tenant_id' => ['char(36)', 'uuid'],
            'user_id' => ['char(36)', 'uuid'],
        ],
        'orders' => [
            'tenant_id' => ['char(36)', 'uuid'],
            'user_id' => ['char(36)', 'uuid'],
        ],
        'appointments' => [
            'tenant_id' => ['char(36)', 'uuid'],
            'user_id' => ['char(36)', 'uuid'],
        ],
        'favorites' => [
            'user_id' => ['char(36)', 'uuid'],
            'favoritable_id' => ['char', 'varchar', 'int', 'bigint'],
        ],
        'notifications' => [
            'id' => ['char(36)', 'uuid'],
            'notifiable_id' => ['char(36)', 'uuid'],
        ],
        'one_time_passwords' => [
            'id' => ['char(36)', 'uuid'],
            'tenant_id' => ['char(36)', 'uuid'],
            'user_id' => ['char(36)', 'uuid'],
        ],
        'tenant_onboarding_states' => [
            'tenant_id' => ['char(36)', 'uuid'],
            'user_id' => ['char(36)', 'uuid'],
        ],
        'inventory_holds' => [
            'id' => ['char(36)', 'uuid', 'int', 'bigint'],
            'tenant_id' => ['char(36)', 'uuid'],
            'location_id' => ['char(36)', 'uuid'],
            'product_id' => ['int', 'bigint'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Foreign key governance contracts
    |--------------------------------------------------------------------------
    */
    'fk_contracts' => [
        [
            'table' => 'users',
            'column' => 'tenant_id',
            'references' => ['table' => 'tenants', 'column' => 'id'],
            'on_delete' => 'set null',
            'nullable' => true,
        ],
        [
            'table' => 'tenant_memberships',
            'column' => 'tenant_id',
            'references' => ['table' => 'tenants', 'column' => 'id'],
            'on_delete' => 'cascade',
            'nullable' => false,
        ],
        [
            'table' => 'tenant_memberships',
            'column' => 'user_id',
            'references' => ['table' => 'users', 'column' => 'id'],
            'on_delete' => 'cascade',
            'nullable' => false,
        ],
        [
            'table' => 'favorites',
            'column' => 'user_id',
            'references' => ['table' => 'users', 'column' => 'id'],
            'on_delete' => 'cascade',
            'nullable' => false,
        ],
        [
            'table' => 'tenant_visits',
            'column' => 'tenant_id',
            'references' => ['table' => 'tenants', 'column' => 'id'],
            'on_delete' => 'cascade',
            'nullable' => false,
        ],
        [
            'table' => 'interactions',
            'column' => 'tenant_id',
            'references' => ['table' => 'tenants', 'column' => 'id'],
            'on_delete' => 'cascade',
            'nullable' => false,
        ],
        [
            'table' => 'interactions',
            'column' => 'product_id',
            'references' => ['table' => 'products', 'column' => 'id'],
            'on_delete' => 'set null',
            'nullable' => true,
        ],
        [
            'table' => 'inventory_holds',
            'column' => 'tenant_id',
            'references' => ['table' => 'tenants', 'column' => 'id'],
            'on_delete' => 'cascade',
            'nullable' => false,
        ],
        [
            'table' => 'inventory_holds',
            'column' => 'product_id',
            'references' => ['table' => 'products', 'column' => 'id'],
            'on_delete' => 'cascade',
            'nullable' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nullability governance (strict gradual)
    |--------------------------------------------------------------------------
    */
    'nullability_contracts' => [
        'required_now' => [
            'tenants' => [
                'id',
                'name',
                'slug',
                'plan',
                'plan_type',
                'business_type',
                'subscription_status',
                'status',
            ],
            'users' => [
                'id',
                'name',
                'email',
                'password',
                'role',
            ],
            'tenant_memberships' => [
                'id',
                'tenant_id',
                'user_id',
                'role',
            ],
            'orders' => [
                'id',
                'order_number',
                'subtotal',
                'discount',
                'delivery_fee',
                'total',
                'payment_status',
                'status',
            ],
            'favorites' => [
                'id',
                'user_id',
                'favoritable_id',
                'favoritable_type',
            ],
            'menu_sections' => [
                'id',
                'tenant_id',
                'name',
                'is_active',
                'sort_order',
            ],
            'interactions' => [
                'id',
                'tenant_id',
                'type',
            ],
            'tenant_visits' => [
                'id',
                'tenant_id',
                'device_type',
            ],
            'inventory_holds' => [
                'id',
                'tenant_id',
                'product_id',
                'session_id',
                'quantity',
                'expires_at',
            ],
            'notifications' => [
                'id',
                'type',
                'notifiable_type',
                'notifiable_id',
                'data',
            ],
            'appointments' => [
                'id',
                'tenant_id',
                'service_id',
                'service_provider_id',
                'scheduled_at',
                'end_at',
                'status',
            ],
        ],
        'optional_legit' => [
            'orders' => [
                'customer_name',
                'customer_phone',
                'customer_email',
                'delivery_address',
                'location_id',
                'tracking_uuid',
            ],
            'appointments' => [
                'user_id',
                'customer_profile_id',
                'customer_name',
                'customer_email',
                'customer_phone',
                'customer_notes',
                'staff_notes',
                'reminder_sent_at',
            ],
            'tenant_memberships' => [
                'preset_key',
                'granted_permissions',
                'accepted_at',
            ],
            'tenant_visits' => [
                'ip_address',
                'user_agent',
                'session_hash',
                'referer',
                'visited_at',
            ],
            'interactions' => [
                'visitor_id',
                'session_id',
                'product_id',
                'user_id',
                'metadata',
                'ip_address',
                'user_agent',
                'referrer',
                'url',
            ],
            'inventory_holds' => [
                'location_id',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Semantic duplicate column contracts
    |--------------------------------------------------------------------------
    */
    'duplicate_column_contracts' => [
        [
            'table' => 'orders',
            'concept' => 'order_cancellation_reason',
            'columns' => ['cancel_reason', 'cancellation_reason'],
            'max_present' => 1,
            'mode' => 'strict',
        ],
        [
            'table' => 'orders',
            'concept' => 'order_delivery_address',
            'columns' => ['customer_address', 'delivery_address'],
            'max_present' => 1,
            'mode' => 'strict',
        ],
        [
            'table' => 'tenants',
            'concept' => 'tenant_cover_media_legacy',
            'columns' => ['cover_path', 'cover_image_path', 'banner_path', 'hero_image'],
            'max_present' => 4,
            'mode' => 'strict',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Critical index/unique invariants
    |--------------------------------------------------------------------------
    */
    'index_invariants' => [
        [
            'table' => 'orders',
            'columns' => ['tenant_id', 'status', 'created_at'],
            'unique' => false,
        ],
        [
            'table' => 'orders',
            'columns' => ['order_number'],
            'unique' => true,
        ],
        [
            'table' => 'favorites',
            'columns' => ['user_id', 'favoritable_id', 'favoritable_type'],
            'unique' => true,
        ],
        [
            'table' => 'tenant_memberships',
            'columns' => ['tenant_id', 'user_id'],
            'unique' => true,
        ],
        [
            'table' => 'tenant_visits',
            'columns' => ['tenant_id', 'created_at'],
            'unique' => false,
        ],
        [
            'table' => 'interactions',
            'columns' => ['tenant_id', 'type', 'created_at'],
            'unique' => false,
        ],
        [
            'table' => 'notifications',
            'columns' => ['notifiable_type', 'notifiable_id'],
            'unique' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON governance contracts (strict gradual)
    |--------------------------------------------------------------------------
    */
    'json_contracts' => [
        [
            'table' => 'interactions',
            'column' => 'metadata',
            'allowed_types' => ['json', 'text', 'longtext'],
            'max_bytes' => 16384,
            'required_keys' => [],
            'mode' => 'strict',
        ],
        [
            'table' => 'tenants',
            'column' => 'theme_config',
            'allowed_types' => ['json', 'text', 'longtext'],
            'max_bytes' => 32768,
            'required_keys' => [],
            'mode' => 'strict',
        ],
        [
            'table' => 'tenants',
            'column' => 'landing_page_data',
            'allowed_types' => ['json', 'text', 'longtext'],
            'max_bytes' => 65535,
            'required_keys' => [],
            'mode' => 'strict',
        ],
        [
            'table' => 'notifications',
            'column' => 'data',
            'allowed_types' => ['json', 'text', 'longtext'],
            'max_bytes' => 32768,
            'required_keys' => [],
            'mode' => 'strict',
        ],
        [
            'table' => 'tenant_onboarding_states',
            'column' => 'milestones',
            'allowed_types' => ['json', 'text', 'longtext'],
            'max_bytes' => 32768,
            'required_keys' => [],
            'mode' => 'strict',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deprecation governance contracts
    |--------------------------------------------------------------------------
    */
    'deprecation_contracts' => [
        [
            'table' => 'orders',
            'forbidden_columns' => ['customer_address', 'cancel_reason', 'discount_amount'],
            'mode' => 'strict',
        ],
    ],
];
