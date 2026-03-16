<?php

declare(strict_types=1);

/**
 * TenantGovernanceServiceProvider - Domain Wiring
 *
 * PURPOSE: Register bindings, events, and policies for Tenant governance.
 * MIGRATED: From App\Domain\TenantGovernance to App\Providers\Domain (2026-01-07)
 */

namespace App\Providers\Domain;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Infrastructure\Access\Guards\StandardTenantPermissionGuard;
use App\Infrastructure\TenantGovernance\Capabilities\ConfigCapabilityManager;
use Illuminate\Support\ServiceProvider;

final class TenantGovernanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CapabilityManagerInterface::class, ConfigCapabilityManager::class);
        $this->app->bind(TenantPermissionGuardInterface::class, StandardTenantPermissionGuard::class);
    }

    public function boot(): void
    {
        // Register scheduled jobs, event listeners
    }
}
