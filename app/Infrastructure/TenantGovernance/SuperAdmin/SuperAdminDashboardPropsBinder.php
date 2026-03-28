<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

final class SuperAdminDashboardPropsBinder
{
    /**
     * @param  object  $target
     * @param  array<string, mixed>  $state
     */
    public function bind(object $target, array $state): void
    {
        foreach ($state as $property => $value) {
            $target->{$property} = $value;
        }
    }
}
