<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureTenantOwnership - Perimeter Guard Middleware
 * 
 * ══════════════════════════════════════════════════════════════════════════════
 * DEFENSE IN DEPTH - LAST LINE OF DEFENSE
 * ══════════════════════════════════════════════════════════════════════════════
 * 
 * This middleware intercepts all route-model-bound resources and verifies
 * that they belong to the authenticated user's tenant BEFORE the controller
 * receives the request.
 * 
 * ATTACK PREVENTION:
 * - IDOR even if developer forgets to check ownership in controller
 * - Provides uniform security across all tenant-scoped routes
 * - Logs suspicious access attempts for security auditing
 * 
 * USAGE:
 * Route::middleware('tenant.ownership')->group(function () {
 *     Route::resource('products', ProductController::class);
 * });
 * 
 * Or in Kernel.php under $middlewareAliases:
 * 'tenant.ownership' => \App\Http\Middleware\EnsureTenantOwnership::class,
 */
class EnsureTenantOwnership
{
    /**
     * Models that should be checked for tenant ownership.
     * Only models with tenant_id column are checked.
     */
    protected array $tenantBoundModels = [
        'product' => \App\Models\Product::class,
        'order' => \App\Models\Order::class,
        'coupon' => \App\Models\Coupon::class,
        'menuSection' => \App\Models\MenuSection::class,
        'lead' => \App\Models\Lead::class,
        'campaign' => \App\Models\Campaign::class,
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if no authenticated user
        if (!auth()->check()) {
            return $next($request);
        }

        // Get the user's accessible tenant IDs
        $allowedTenantIds = $this->getUserTenantIds();

        // If user has no tenants, allow (might be admin or buyer)
        if (empty($allowedTenantIds)) {
            return $next($request);
        }

        // Check all route parameters for tenant-bound models
        foreach ($request->route()->parameters() as $key => $value) {
            // Skip non-model values (IDs, slugs, etc.)
            if (!is_object($value)) {
                continue;
            }

            // Check if this is a tenant-bound model
            if ($this->isTenantBoundModel($value)) {
                // Verify ownership
                if (!$this->verifyOwnership($value, $allowedTenantIds)) {
                    // Log the suspicious access attempt
                    $this->logSecurityViolation($request, $value);

                    // Return 404 to prevent information disclosure
                    // (Don't reveal if resource exists but is denied)
                    abort(404);
                }
            }
        }

        return $next($request);
    }

    /**
     * Get the tenant IDs accessible by the current user.
     */
    protected function getUserTenantIds(): array
    {
        $user = auth()->user();

        // User->tenants() returns HasMany
        return $user->tenants()->pluck('id')->toArray();
    }

    /**
     * Check if a model instance is tenant-bound.
     */
    protected function isTenantBoundModel($model): bool
    {
        // Check if model has tenant_id attribute
        if (!isset($model->tenant_id)) {
            return false;
        }

        return true;
    }

    /**
     * Verify that the model belongs to one of the allowed tenants.
     */
    protected function verifyOwnership($model, array $allowedTenantIds): bool
    {
        return in_array($model->tenant_id, $allowedTenantIds);
    }

    /**
     * Log a security violation for auditing.
     */
    protected function logSecurityViolation(Request $request, $model): void
    {
        $data = [
            'event' => 'TENANT_OWNERSHIP_VIOLATION',
            'severity' => 'HIGH',
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(),
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
            'model_type' => get_class($model),
            'model_id' => $model->id ?? null,
            'model_tenant_id' => $model->tenant_id ?? null,
            'timestamp' => now()->toIso8601String(),
        ];

        // Log to security channel if available, fallback to default
        try {
            if (config('logging.channels.security')) {
                \Illuminate\Support\Facades\Log::channel('security')->warning(
                    'SECURITY: Cross-tenant access attempt detected',
                    $data
                );
            } else {
                \Illuminate\Support\Facades\Log::warning(
                    'SECURITY: Cross-tenant access attempt detected',
                    $data
                );
            }
        } catch (\Throwable $e) {
            // Fallback to default log if any error
            \Illuminate\Support\Facades\Log::warning(
                'SECURITY: Cross-tenant access attempt detected',
                $data
            );
        }

        // Optional: Store in database for dashboard
        // SecurityLog::create($data);
    }
}
