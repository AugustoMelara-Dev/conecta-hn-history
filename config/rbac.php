<?php

declare(strict_types=1);

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;

return [
    'roles' => [
        Role::OWNER->value => array_map(
            static fn (Permission $permission): string => $permission->value,
            Permission::cases(),
        ),

        Role::ADMIN->value => [
            Permission::CRM_DEALS_VIEW->value,
            Permission::CRM_DEALS_CREATE->value,
            Permission::CRM_DEALS_UPDATE->value,
            Permission::CRM_DEALS_MOVE_STAGE->value,
            Permission::CRM_DEALS_DELETE->value,
            Permission::CRM_REPORTS_VIEW->value,
        ],

        Role::MANAGER->value => [
            Permission::CRM_DEALS_VIEW->value,
            Permission::CRM_DEALS_CREATE->value,
            Permission::CRM_DEALS_UPDATE->value,
            Permission::CRM_DEALS_MOVE_STAGE->value,
            Permission::CRM_REPORTS_VIEW->value,
        ],

        Role::OPERATOR->value => [
            Permission::CRM_DEALS_VIEW->value,
            Permission::CRM_REPORTS_VIEW->value,
        ],

        Role::VIEWER->value => [
            Permission::CRM_DEALS_VIEW->value,
            Permission::CRM_REPORTS_VIEW->value,
        ],
    ],
];
