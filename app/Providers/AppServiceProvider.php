<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

// Models
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Lead;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;

// Policies
use App\Policies\CampaignPolicy;
use App\Policies\CouponPolicy;
use App\Policies\LeadPolicy;
use App\Policies\MenuSectionPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;

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

        // ═══════════════════════════════════════════════════════════════════════
        // EXPLICIT POLICY BINDING - PRODUCTION DETERMINISM
        // ═══════════════════════════════════════════════════════════════════════
        //
        // Explicitly bind Model-Policy pairs instead of relying on Laravel's
        // auto-discovery magic. Benefits:
        // - Zero overhead from class reflection on each request
        // - Works correctly even with config:cache in production
        // - Deterministic behavior regardless of naming conventions
        // - Easier debugging when authorization fails
        //
        // SECURITY: All tenant-aware policies extend TenantAwarePolicy
        // which enforces tenant ownership checks.
        // ═══════════════════════════════════════════════════════════════════════

        // Tenant-Aware Models (have tenant_id, use TenantAwarePolicy)
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(MenuSection::class, MenuSectionPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);

        // System-Wide Models (ownership based on user relationship)
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // ═══════════════════════════════════════════════════════════════════════
        // QUEUE WORKER MEMORY HYGIENE - STATE POLLUTION PREVENTION
        // ═══════════════════════════════════════════════════════════════════════
        //
        // Queue Workers (php artisan queue:work) are long-running daemon processes.
        // Static properties in Observers persist across job executions, causing
        // "state pollution" where Job B reads stale data cached by Job A.
        //
        // DESIGN PATTERN: Interface Segregation + Open/Closed Principle
        // Instead of hardcoding Observer class names, we iterate over classes
        // that implement ResetsStaticCache interface. Adding new Observers
        // requires only adding them to this array - no code changes needed.
        //
        // PERFORMANCE IMPACT: Negligible (~0.01ms per job per class)
        // ═══════════════════════════════════════════════════════════════════════

        // Registry of all classes with static state that needs cleanup
        // Add new classes here when they implement ResetsStaticCache
        $resettableClasses = [
            \App\Observers\ProductObserver::class,
            // Future: Add other observers with static caches here
        ];

        Queue::after(function ($event) use ($resettableClasses) {
            // Polymorphic cleanup: iterate over all resettable classes
            foreach ($resettableClasses as $class) {
                if (is_a($class, \App\Contracts\ResetsStaticCache::class, true)) {
                    $class::flushStaticCache();
                }
            }
        });
    }
}
