<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Microsite CRM Rollout
    |--------------------------------------------------------------------------
    |
    | Public microsite CRM visibility is protected by two gates:
    | 1) Tenant feature/capability gate (TenantFeature::CRM_PIPELINES).
    | 2) Rollout gate (allowlist + percentage cohort).
    |
    | Fail-closed policy: when either gate is false, no CRM data is exposed.
    |
    */
    'enabled' => true,

    // Tenant IDs or slugs explicitly allowed during rollout.
    'allowlist' => [
        // 'tenant-id-uuid',
        // 'my-business-slug',
    ],

    // 0-100 inclusive. 0 means allowlist-only, 100 means all tenants that pass feature gate.
    'percentage' => 0,
];

