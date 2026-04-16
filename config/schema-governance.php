<?php

declare(strict_types=1);

return [
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
];
