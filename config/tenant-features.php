<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantFeature;

return [
    'features' => [
        TenantFeature::ADVANCED_CATALOG->value => [
            'plans' => ['pro'],
            'all_capabilities' => [
                Capability::CATALOG_BASIC->value,
            ],
        ],

        TenantFeature::MARKETING_SUITE->value => [
            'plans' => ['pro'],
            'all_capabilities' => [
                Capability::CATALOG_BASIC->value,
                Capability::NOTIFICATIONS_BASIC->value,
            ],
        ],

        TenantFeature::MARKETING_CAMPAIGNS->value => [
            'plans' => ['pro'],
            'all_capabilities' => [
                Capability::NOTIFICATIONS_BASIC->value,
            ],
        ],

        TenantFeature::PROMOTIONAL_COUPONS->value => [
            'plans' => ['pro'],
            'all_capabilities' => [
                Capability::COUPONS_BASIC->value,
            ],
        ],

        TenantFeature::LOYALTY_PROGRAM->value => [
            'plans' => ['pro'],
            'all_capabilities' => [
                Capability::CUSTOMER_DIRECTORY->value,
            ],
        ],

        TenantFeature::LOYALTY_REWARDS->value => [
            'plans' => ['pro'],
            'all_capabilities' => [
                Capability::CUSTOMER_DIRECTORY->value,
            ],
        ],

        TenantFeature::ADVANCED_BRANDING->value => [
            'plans' => ['pro'],
            'all_capabilities' => [
                Capability::CATALOG_BASIC->value,
            ],
        ],

        TenantFeature::UPGRADE_PROMPT->value => [
            'plans' => ['semilla'],
        ],
    ],
];
