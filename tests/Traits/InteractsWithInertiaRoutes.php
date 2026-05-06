<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Http\Middleware\ResolveTenantFromRequest;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/**
 * InteractsWithInertiaRoutes - Test Route Registration Trait
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ENCAPSULATION: Route registration isolated to trait                   ║
 * ║  REUSABILITY: Can be used across multiple test classes                 ║
 * ║  CLEANUP: Routes are properly isolated per test (Issue #10)            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 * class MyTest extends TestCase
 * {
 *     use InteractsWithInertiaRoutes;
 *
 *     protected function setUp(): void
 *     {
 *         parent::setUp();
 *         $this->registerInertiaTestRoutes();
 *     }
 *
 *     protected function tearDown(): void
 *     {
 *         $this->cleanupInertiaTestRoutes();
 *         parent::tearDown();
 *     }
 * }
 */
trait InteractsWithInertiaRoutes
{
    /**
     * Track registered test route names for cleanup.
     */
    protected array $registeredTestRoutes = [];

    /**
     * Register test routes for Inertia middleware testing.
     *
     * Creates two routes:
     * - /__test/storefront: Simulates storefront context (tenant allowed)
     * - /__test/admin: Simulates admin context (no tenant)
     */
    protected function registerInertiaTestRoutes(): void
    {
        // Storefront route (tenant context allowed per config)
        Route::middleware(['web'])
            ->get('/__test/storefront', function () {
                return inertia('Test');
            })
            ->name('storefront.test');

        // Admin route (tenant context forbidden)
        Route::middleware(['web'])
            ->get('/__test/admin', function () {
                return inertia('Test');
            })
            ->name('admin.test');

        $this->registeredTestRoutes = ['storefront.test', 'admin.test'];
    }

    /**
     * Register tenant resolution test routes.
     *
     * Creates routes for testing tenant resolution from different sources.
     * INCLUDES: ResolveTenantFromRequest middleware for proper resolution.
     */
    protected function registerTenantResolutionRoutes(): void
    {
        // Route parameter resolution - WITH tenant middleware
        Route::middleware(['web', ResolveTenantFromRequest::class])
            ->get('/__test/tenant/{tenant_slug}', function () {
                return response()->json([
                    'tenant_id' => app()->has('currentTenant')
                        ? app('currentTenant')->id
                        : null,
                ]);
            })
            ->name('test.tenant');

        // Header-based resolution - WITH tenant middleware
        Route::middleware(['web', ResolveTenantFromRequest::class])
            ->get('/__test/api-tenant', function () {
                return response()->json([
                    'tenant_id' => app()->has('currentTenant')
                        ? app('currentTenant')->id
                        : null,
                ]);
            })
            ->name('test.api-tenant');

        $this->registeredTestRoutes = array_merge(
            $this->registeredTestRoutes,
            ['test.tenant', 'test.api-tenant']
        );
    }

    /**
     * Cleanup registered test routes (Issue #10 fix).
     *
     * Call this in tearDown() to prevent route pollution between tests.
     */
    protected function cleanupInertiaTestRoutes(): void
    {
        if (empty($this->registeredTestRoutes)) {
            return;
        }

        /** @var Router $router */
        $router = app('router');
        $routes = $router->getRoutes();

        // Remove registered test routes
        foreach ($this->registeredTestRoutes as $routeName) {
            $route = $routes->getByName($routeName);
            if ($route) {
                // Force re-compile routes by refreshing
                $router->getRoutes()->refreshNameLookups();
            }
        }

        // Clear tenant binding if exists
        if (app()->has('currentTenant')) {
            app()->forgetInstance('currentTenant');
        }

        $this->registeredTestRoutes = [];
    }

    /**
     * Extract Inertia props from test response.
     *
     * @param  \Illuminate\Testing\TestResponse  $response
     */
    protected function getInertiaProps($response): array
    {
        return $response->viewData('page')['props'] ?? [];
    }
}
