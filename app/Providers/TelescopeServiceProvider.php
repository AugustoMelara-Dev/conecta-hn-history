<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
    class TelescopeServiceProvider extends \Laravel\Telescope\TelescopeApplicationServiceProvider
    {
        public function register(): void
        {
            if (! config('telescope.enabled', false)) {
                return;
            }

            \Laravel\Telescope\Telescope::night();

            $this->hideSensitiveRequestDetails();

            \Laravel\Telescope\Telescope::filter(function (\Laravel\Telescope\IncomingEntry $entry) {
                if ($this->app->environment('local')) {
                    return true;
                }

                return $entry->isReportableException() ||
                    $entry->isFailedRequest() ||
                    $entry->isFailedJob() ||
                    $entry->isScheduledTask() ||
                    $entry->hasMonitoredTag();
            });
        }

        protected function hideSensitiveRequestDetails(): void
        {
            if ($this->app->environment('local')) {
                return;
            }

            \Laravel\Telescope\Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation']);
            \Laravel\Telescope\Telescope::hideRequestHeaders([
                'cookie',
                'x-csrf-token',
                'x-xsrf-token',
                'authorization',
            ]);
        }

        protected function gate(): void
        {
            \Illuminate\Support\Facades\Gate::define('viewTelescope', function ($user) {
                $superAdmins = config('auth.super_admins', []);
                $hasSuperAdminRole = isset($user->role) && $user->role === 'super_admin';
                $isWhitelisted = in_array($user->email ?? '', $superAdmins, true);

                return ($hasSuperAdminRole && $isWhitelisted) || $isWhitelisted;
            });
        }
    }
} else {
    class TelescopeServiceProvider extends ServiceProvider {}
}
