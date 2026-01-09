<?php

declare(strict_types=1);

/**
 * TenantGovernanceServiceProvider - Domain Wiring
 *
 * PURPOSE: Register bindings, events, and policies for Tenant governance.
 * MIGRATED: From App\Domain\TenantGovernance to App\Providers\Domain (2026-01-07)
 */

namespace App\Providers\Domain;

use Illuminate\Support\ServiceProvider;

final class TenantGovernanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind domain contracts
    }

    public function boot(): void
    {
        // Register scheduled jobs, event listeners
    }
}
