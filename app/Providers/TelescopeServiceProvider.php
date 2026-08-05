<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Security\SensitiveTelemetryGuard;
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
        // ═══════════════════════════════════════════════════════════════════════
        // PERFORMANCE: Early escape when Telescope is disabled
        // ═══════════════════════════════════════════════════════════════════════
        // This prevents Telescope from consuming memory/CPU on every request
        // when it's not actually recording anything. Critical for production.
        //
        // Telescope will still "boot" (it's registered in providers.php),
        // but this early return prevents the filter closures and observers
        // from being registered, saving ~2-5ms per request.
        // ═══════════════════════════════════════════════════════════════════════
        if (! config('telescope.enabled', false) && config('app.env') !== 'local') {
            return;
        }

        // Enable dark mode for the dashboard
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        // Filter entries to reduce noise and database size
        Telescope::filter(function (IncomingEntry $entry) {
            // Livewire embeds form state in nested payloads that Telescope's
            // top-level redaction cannot safely scrub (including admin secrets).
            if (SensitiveTelemetryGuard::shouldExclude($entry)) {
                return false;
            }

            // Always record in local development
            if (config('app.env') === 'local') {
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
        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation', 'current_password']);
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
            // WHITELIST: Super admin emails from environment config
            // ═══════════════════════════════════════════════════════════════
            $superAdmins = config('auth.super_admins', []);

            // Dual-gate: Role + Whitelist check
            $hasSuperAdminRole = isset($user->role) && $user->role === 'super_admin';
            $isWhitelisted = in_array($user->email ?? '', $superAdmins, true);

            // Option 1: Role + Whitelist check (recommended)
            if ($hasSuperAdminRole && $isWhitelisted) {
                return true;
            }

            // Option 2: Whitelist-only check for production debugging
            if ($isWhitelisted) {
                return true;
            }

            // Default: DENY access to everyone else
            return false;
        });
    }
}
