<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Lead;
use App\Models\MenuSection;
// Models
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CampaignPolicy;
use App\Policies\CouponPolicy;
use App\Policies\LeadPolicy;
use App\Policies\MenuSectionPolicy;
// Policies
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
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

        // ═══════════════════════════════════════════════════════════════════════
        // GRANULAR RATE LIMITING - TRAFFIC SHAPING ARCHITECTURE
        // ═══════════════════════════════════════════════════════════════════════
        //
        // Defense-in-depth against three attack vectors:
        // 1. SMS Pumping (OTP generation abuse)
        // 2. Dictionary Attacks (code enumeration)
        // 3. Resource Exhaustion (database flooding)
        //
        // Each limiter implements IP-based throttling with structured JSON errors.
        // ═══════════════════════════════════════════════════════════════════════

        $this->configureRateLimiters();

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
        // MODEL OBSERVERS - CACHE INVALIDATION & DATA INTEGRITY
        // ═══════════════════════════════════════════════════════════════════════
        //
        // Observer Pattern: Listen for model state changes and trigger side effects.
        //
        // CACHE INVALIDATION (Generational Versioning):
        // - ProductObserver: Rotates tenant version on product changes
        // - CouponObserver: Rotates tenant version on coupon changes
        // - MenuSectionObserver: Rotates tenant version on category changes
        //                        (Cascading Invalidation for stale relationships)
        //
        // DATA INTEGRITY:
        // - ProductObserver: Auto-assigns city_id from tenant, triggers blurhash
        //
        // NOTE: MenuSection invalidation is critical because ProductController
        // eager-loads 'menuSection' - category name changes would otherwise be
        // stale in the product cache for up to 60 minutes.
        // ═══════════════════════════════════════════════════════════════════════
        Product::observe(\App\Observers\ProductObserver::class);
        Coupon::observe(\App\Observers\CouponObserver::class);
        MenuSection::observe(\App\Observers\MenuSectionObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);

        // ═══════════════════════════════════════════════════════════════════════
        // EVENT LISTENERS - INVENTORY ALERTING
        // ═══════════════════════════════════════════════════════════════════════
        // LowStockThresholdReached: Dispatched when product stock falls below threshold.
        // SendLowStockNotification: Rate-limited listener (max 1 alert per product/day).
        // ═══════════════════════════════════════════════════════════════════════
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\LowStockThresholdReached::class,
            \App\Listeners\SendLowStockNotification::class
        );

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

    /**
     * Configure granular rate limiters for API security.
     *
     * ARCHITECTURE: Named Rate Limiters for Traffic Shaping
     *
     * Each limiter targets a specific attack vector with mathematically
     * chosen thresholds based on legitimate human behavior patterns.
     */
    protected function configureRateLimiters(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // COMPONENT 1: FINANCIAL FIREWALL (SMS Pumping Defense)
        // ═══════════════════════════════════════════════════════════════════════
        //
        // THREAT: SMS Pumping fraud drains SMS provider budget by triggering
        //         thousands of OTP requests to premium-rate numbers.
        //
        // CONSTRAINT: 3 requests per 60 seconds per IP
        //
        // RATIONALE: No legitimate user needs >3 SMS codes in 1 minute.
        //            Any exceeding velocity is structurally malicious.
        //
        // APPLIED TO: POST /api/otp/generate
        // ═══════════════════════════════════════════════════════════════════════
        RateLimiter::for('api.otp-generation', function (Request $request) {
            return Limit::perMinute(3)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => 'Demasiados intentos de SMS. Por favor espera.',
                        'retry_after_seconds' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // ═══════════════════════════════════════════════════════════════════════
        // COMPONENT 2: ANTI-ENUMERATION SHIELD (Dictionary Attack Defense)
        // ═══════════════════════════════════════════════════════════════════════
        //
        // THREAT: Brute-force/dictionary attacks against verification endpoints
        //         to enumerate valid OTPs or discount coupon codes.
        //
        // CONSTRAINT: 5 attempts per 60 seconds per IP
        //
        // RATIONALE: Based on human typing speed - legitimate users cannot
        //            physically type, verify, and correct codes faster.
        //            5 attempts allow for typo corrections while making
        //            brute-force mathematically infeasible (time-to-crack → ∞)
        //
        // APPLIED TO:
        //   - POST /api/otp/verify (account protection)
        //   - POST /api/coupons/verify (discount code protection)
        // ═══════════════════════════════════════════════════════════════════════
        RateLimiter::for('api.verification', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'VERIFICATION_RATE_LIMIT',
                        'message' => 'Demasiados intentos de verificación. Espera un momento.',
                        'retry_after_seconds' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // ═══════════════════════════════════════════════════════════════════════
        // COMPONENT 3: TRANSACTIONAL WRITE PROTECTION (Database Integrity)
        // ═══════════════════════════════════════════════════════════════════════
        //
        // THREAT: Resource exhaustion attacks flooding the orders table with
        //         garbage records, consuming database CPU/locks/disk.
        //
        // CONSTRAINT: 10 orders per 60 seconds per IP
        //
        // RATIONALE: Generous for power users (family orders, catering) but
        //            strict enough to prevent botnet flooding. No legitimate
        //            customer creates >10 orders per minute.
        //
        // APPLIED TO: POST /api/orders
        // ═══════════════════════════════════════════════════════════════════════
        RateLimiter::for('api.transactions', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'TRANSACTION_RATE_LIMIT',
                        'message' => 'Demasiados pedidos. Por favor espera antes de crear otro.',
                        'retry_after_seconds' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'API_RATE_LIMIT',
                        'message' => 'Demasiadas solicitudes. Por favor espera.',
                        'retry_after_seconds' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // ═══════════════════════════════════════════════════════════════════════
        // TIERED TRAFFIC SHAPING: Dynamic limits based on tenant subscription
        // ═══════════════════════════════════════════════════════════════════════
        // FREE TIER:  60 requests/minute  - Prevents "Noisy Neighbor" DoS
        // PRO TIER:  300 requests/minute  - Business-critical operations
        // PUBLIC:     60 requests/minute  - Safe baseline for unauthenticated
        //
        // APPLIED TO: Routes using 'throttle:api.tenant' middleware
        // ═══════════════════════════════════════════════════════════════════════
        RateLimiter::for('api.tenant', function (Request $request) {
            $user = $request->user();

            // No authenticated user: public baseline
            if (! $user) {
                return Limit::perMinute(60)
                    ->by($request->ip())
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Demasiadas solicitudes. Por favor espera.',
                            'tier' => 'public',
                            'retry_after_seconds' => $headers['Retry-After'] ?? 60,
                        ], 429, $headers);
                    });
            }

            // Resolve tenant subscription tier
            $tenant = $user->tenant;

            // ═══════════════════════════════════════════════════════════════════════
            // SECURITY: User without tenant falls back to IP-based limiting
            // ═══════════════════════════════════════════════════════════════════════
            // NEVER use user->id as fallback - this allows multi-user bypass attack
            // where a Free tenant creates 50 users to get 50x the rate limit.
            // ═══════════════════════════════════════════════════════════════════════
            if (! $tenant) {
                return Limit::perMinute(60)
                    ->by('ip:' . $request->ip())
                    ->response(function (Request $request, array $headers) {
                        return response()->json([
                            'success' => false,
                            'error_code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Usuario sin tenant. Límite por IP aplicado.',
                            'tier' => 'orphan',
                            'retry_after_seconds' => $headers['Retry-After'] ?? 60,
                        ], 429, $headers);
                    });
            }

            $isPro = $tenant->is_pro ?? false;

            // Dynamic limit based on tier
            $limit = $isPro ? 300 : 60;
            $tier = $isPro ? 'pro' : 'free';

            // ═══════════════════════════════════════════════════════════════════════
            // KEY: Strictly by Tenant ID - prevents multi-user bypass attack
            // ═══════════════════════════════════════════════════════════════════════
            return Limit::perMinute($limit)
                ->by('tenant:' . $tenant->id)
                ->response(function (Request $request, array $headers) use ($tier, $limit) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'TENANT_RATE_LIMIT',
                        'message' => 'Límite de solicitudes alcanzado para tu plan.',
                        'tier' => $tier,
                        'limit' => $limit,
                        'retry_after_seconds' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });
    }
}
