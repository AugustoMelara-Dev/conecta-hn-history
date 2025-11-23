<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        if (app()->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Support\Facades\Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Tenant::class, \App\Policies\TenantPolicy::class);

        // Register ProductObserver for city_id auto-assignment
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\Tenant::observe(\App\Observers\TenantObserver::class);
        \App\Models\City::observe(\App\Observers\CityObserver::class);

        // STRICT MODE: Prevent N+1 and silent failures in development
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(!app()->isProduction());
        \Illuminate\Database\Eloquent\Model::preventSilentlyDiscardingAttributes(!app()->isProduction());
    }
}
