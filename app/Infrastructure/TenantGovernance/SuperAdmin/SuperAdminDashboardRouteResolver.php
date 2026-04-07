<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

final class SuperAdminDashboardRouteResolver
{
    public function resolve(string $name, array $parameters = []): string
    {
        try {
            return route($name, $parameters, false);
        } catch (\Throwable) {
            return '#';
        }
    }

    public function callback(): callable
    {
        return $this->resolve(...);
    }
}
