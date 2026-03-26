<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardPropsBinder
{
    /**
     * @param  object  $target
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $defaults
     */
    public function bind(object $target, array $state, array $defaults): void
    {
        foreach ($defaults as $property => $default) {
            $target->{$property} = $state[$property] ?? $default;
        }
    }
}
