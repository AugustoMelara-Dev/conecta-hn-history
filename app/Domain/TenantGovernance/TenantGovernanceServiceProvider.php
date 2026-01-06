<?php

declare(strict_types=1);

/**
 * TenantGovernanceServiceProvider - Domain Wiring
 *
 * PURPOSE: Register bindings, events, and policies for Tenant governance.
 * Handles tenant limits, audits, and stale order cleanup.
 */

namespace App\Domain\TenantGovernance;

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
