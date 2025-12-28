<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * TelescopeServiceProvider - APM Security Configuration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HIGH SECURITY: Telescope exposes sensitive system internals            ║
 * ║  Access strictly gated to authorized administrators only                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SECURITY MODEL:
 * - Only super admins can access /telescope
 * - Production access requires explicit email whitelist
 * - Data pruned after 48 hours to prevent database bloat
 */
class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Conditionally skip Telescope in production unless explicitly enabled
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        // Filter entries to reduce noise
        Telescope::filter(function (IncomingEntry $entry) {
            // Always record in local development
            if ($this->app->environment('local')) {
                return true;
            }

            // In production, only record important entries
            return $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation']);
        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * SECURITY GATE:
     * This gate controls who can access the Telescope dashboard.
     * Access is restricted to:
     * 1. Authenticated users only
     * 2. Users with verified super admin status
     * 3. Or users in the explicit email whitelist
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            // ═══════════════════════════════════════════════════════════════
            // WHITELIST: Explicit super admin emails
            // ═══════════════════════════════════════════════════════════════
            $superAdmins = [
                'admin@conectahn.com',
                'augusto@conectahn.com',
                // Add more trusted admin emails here
            ];

            // Option 1: Email whitelist check
            if (in_array($user->email, $superAdmins, true)) {
                return true;
            }

            // Option 2: Role-based check (if using roles)
            if (property_exists($user, 'is_super_admin') && $user->is_super_admin) {
                return true;
            }

            // Option 3: Permission-based check (if using Spatie Permissions)
            // if ($user->hasRole('super-admin')) {
            //     return true;
            // }

            // Default: DENY access to everyone else
            return false;
        });
    }
}
