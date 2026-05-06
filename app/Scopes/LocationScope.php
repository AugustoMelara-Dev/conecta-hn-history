<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * LocationScope - Session-Driven Geofencing Filter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Localized User Experience                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * This scope provides a "virtual sharding" experience based on user's
 * geographical preference stored in session. Users see only content
 * relevant to their selected city.
 *
 * BEHAVIOR MODES:
 * - State A (Localized): Session has city_id → strict WHERE city_id = ?
 * - State B (National):  Session empty → no filter, full dataset visible
 *
 * SESSION KEY: 'user_selected_city_id' (or 'active_city_id' legacy)
 *
 * DEFENSIVE DESIGN:
 * - Safe session access with try-catch
 * - Handles missing session driver gracefully (unit tests, queue jobs)
 * - Bypasses in console and admin contexts
 */
class LocationScope implements Scope
{
    /**
     * Session key for the user's selected city.
     */
    protected const SESSION_KEY_PRIMARY = 'user_selected_city_id';

    protected const SESSION_KEY_LEGACY = 'active_city_id';

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 1: Console Protection (Artisan, Seeders, Queue Workers)
        // ═══════════════════════════════════════════════════════════════════════
        if (app()->runningInConsole()) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 2: Filament Panel Protection (Admin & App Dashboards)
        // ═══════════════════════════════════════════════════════════════════════
        // Business owners need to see products from ALL cities in their dashboard.
        // Location filtering is only for public storefront experience.
        // ═══════════════════════════════════════════════════════════════════════
        if ($this->isFilamentContext()) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 3: Route-Based Admin Detection (Fallback)
        // ═══════════════════════════════════════════════════════════════════════
        if ($this->isAdminRoute()) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 4: Session Availability Check (Defensive)
        // ═══════════════════════════════════════════════════════════════════════
        // Session may be unavailable during:
        // - Unit tests without session middleware
        // - Queue jobs processed in isolation
        // - API requests without session driver
        // ═══════════════════════════════════════════════════════════════════════
        $cityId = $this->getSelectedCityIdFromSession();

        // State B: National View (no city selected = show all)
        if ($cityId === null) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // State A: Localized View (city selected = filter by city)
        // ═══════════════════════════════════════════════════════════════════════
        $builder->where($model->getTable().'.city_id', $cityId);
    }

    /**
     * Safely retrieve the selected city ID from session.
     *
     * DEFENSIVE DESIGN:
     * - Wraps session access in try-catch
     * - Checks both new and legacy session keys
     * - Returns null on any failure (fail-open for location)
     */
    protected function getSelectedCityIdFromSession(): ?int
    {
        try {
            // Check if session is available
            if (! $this->isSessionAvailable()) {
                return null;
            }

            // Try primary key first
            $cityId = session(self::SESSION_KEY_PRIMARY);
            if ($cityId !== null) {
                return (int) $cityId;
            }

            // Fallback to legacy key
            $cityId = session(self::SESSION_KEY_LEGACY);
            if ($cityId !== null) {
                return (int) $cityId;
            }

            return null;
        } catch (\Throwable $e) {
            // Session access failed (driver unavailable, etc.)
            // Fail-open: return null to show all cities
            return null;
        }
    }

    /**
     * Check if session driver is available and started.
     */
    protected function isSessionAvailable(): bool
    {
        try {
            // Check if request exists and has session
            if (! app()->bound('request')) {
                return false;
            }

            $request = request();

            // Check if session is started
            return $request->hasSession() && $request->session()->isStarted();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if currently in Filament context.
     */
    protected function isFilamentContext(): bool
    {
        if (! class_exists(\Filament\Facades\Filament::class)) {
            return false;
        }

        try {
            return \Filament\Facades\Filament::isServing();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if current route matches admin/app patterns.
     */
    protected function isAdminRoute(): bool
    {
        try {
            return request()->is('admin/*') ||
                   request()->is('admin') ||
                   request()->is('app/*') ||
                   request()->is('app');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
