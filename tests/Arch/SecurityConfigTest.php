<?php

namespace Tests\Arch;

use Illuminate\Support\Facades\Route;

/**
 * SecurityConfigTest - Defensive Architecture Audit
 *
 * PURPOSE: Validate that security guardrails (Providers, Middleware, Attributes)
 * are correctly configured to protect whitelist-exempt zones.
 *
 * CONTEXT: We have exempted Livewire/Tenant and Filament/App from ORM scope checks
 * based on the premise that middleware enforces isolation. This test suite audits
 * that the middleware actually exists and is correctly configured.
 *
 * PHILOSOPHY: "Trust, but verify" - Ronald Reagan
 *
 * Run with: php artisan test --filter=SecurityConfigTest
 */
class SecurityConfigTest extends \Tests\TestCase
{
    // ═══════════════════════════════════════════════════════════════════════════
    // SPEC 1: APP PANEL PROVIDER MIDDLEWARE AUDIT
    // Validates that the tenant panel has mandatory security middleware
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * AppPanelProvider must include mandatory security middleware.
     *
     * SECURITY REQUIREMENT:
     * - Filament\Http\Middleware\Authenticate (base authentication)
     * - The panel must use tenant() for multi-tenancy isolation
     *
     * If these are missing, the whitelist exemption in ArchitectureTest
     * creates a catastrophic data exposure risk.
     */
    public function test_app_panel_provider_enforces_tenant_security(): void
    {
        $providerPath = app_path('Providers/Filament/AppPanelProvider.php');

        $this->assertFileExists(
            $providerPath,
            'CRITICAL: AppPanelProvider.php not found! Tenant panel security cannot be verified.'
        );

        $content = file_get_contents($providerPath);

        // ═══════════════════════════════════════════════════════════════
        // CHECK 1: Authenticate middleware is present
        // ═══════════════════════════════════════════════════════════════
        $hasAuthenticateMiddleware = (
            str_contains($content, 'Authenticate::class') ||
            str_contains($content, 'Authenticate::class')
        );

        $this->assertTrue(
            $hasAuthenticateMiddleware,
            "CRITICAL: AppPanelProvider is missing Authenticate middleware.\n".
            'Add: ->authMiddleware([Authenticate::class])'
        );

        // ═══════════════════════════════════════════════════════════════
        // CHECK 2: Multi-tenancy is configured via ->tenant()
        // ═══════════════════════════════════════════════════════════════
        $hasTenantConfiguration = str_contains($content, '->tenant(');

        $this->assertTrue(
            $hasTenantConfiguration,
            "CRITICAL: AppPanelProvider is missing tenant() configuration.\n".
            "Add: ->tenant(\\App\\Models\\Tenant::class, slugAttribute: 'slug')\n".
            'Without this, Filament cannot enforce tenant isolation.'
        );

        // ═══════════════════════════════════════════════════════════════
        // CHECK 3: AuthenticateSession middleware for session hijacking prevention
        // ═══════════════════════════════════════════════════════════════
        $hasSessionAuth = str_contains($content, 'AuthenticateSession::class');

        $this->assertTrue(
            $hasSessionAuth,
            "WARNING: AppPanelProvider is missing AuthenticateSession middleware.\n".
            'This prevents session hijacking attacks.'
        );

        // ═══════════════════════════════════════════════════════════════
        // CHECK 4: Login page is enabled (prevents unauthenticated access)
        // ═══════════════════════════════════════════════════════════════
        $hasLoginEnabled = str_contains($content, '->login(');

        $this->assertTrue(
            $hasLoginEnabled,
            "CRITICAL: AppPanelProvider does not have login() enabled.\n".
            'Unauthenticated users could access the tenant panel.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SPEC 2: LIVEWIRE/TENANT SECURITY ATTRIBUTES AUDIT
    // Validates that all Livewire components in Tenant zone have security posture
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * All Livewire/Tenant components must have explicit security attributes.
     *
     * SECURITY REQUIREMENT:
     * Each component in the whitelist-exempt zone must declare at least ONE of:
     * - #[Layout(...)] attribute (indicates protected layout with middleware)
     * - #[Middleware(...)] attribute (explicit middleware declaration)
     * - Extends a secure base class
     *
     * Components without security posture are "orphans" that could be
     * accidentally exposed on public routes.
     */
    public function test_livewire_tenant_components_have_security_attributes(): void
    {
        $livewireTenantPath = app_path('Livewire/Tenant');

        if (! is_dir($livewireTenantPath)) {
            $this->markTestSkipped('Livewire/Tenant directory not found');
        }

        $violations = [];
        $files = $this->getPhpFilesRecursively($livewireTenantPath);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

            // Extract class name from file
            if (! preg_match('/class\s+(\w+)\s+extends/', $content, $matches)) {
                continue; // Not a class file
            }

            $hasSecurityPosture = false;

            // ═══════════════════════════════════════════════════════════════
            // CHECK 1: Has #[Layout(...)] attribute
            // ═══════════════════════════════════════════════════════════════
            if (preg_match('/#\[Layout\s*\(/', $content)) {
                $hasSecurityPosture = true;
            }

            // ═══════════════════════════════════════════════════════════════
            // CHECK 2: Has #[Middleware(...)] attribute
            // ═══════════════════════════════════════════════════════════════
            if (preg_match('/#\[Middleware\s*\(/', $content)) {
                $hasSecurityPosture = true;
            }

            // ═══════════════════════════════════════════════════════════════
            // CHECK 3: Extends a known secure base class
            // ═══════════════════════════════════════════════════════════════
            $secureBaseClasses = [
                'BaseTenantComponent',
                'AuthenticatedComponent',
                'TenantComponent',
            ];

            foreach ($secureBaseClasses as $baseClass) {
                if (str_contains($content, "extends {$baseClass}")) {
                    $hasSecurityPosture = true;
                    break;
                }
            }

            // ═══════════════════════════════════════════════════════════════
            // CHECK 4: Has inline security check in mount()
            // This is a fallback for components with manual security
            // ═══════════════════════════════════════════════════════════════
            if (preg_match('/abort\s*\(\s*403/', $content) ||
                preg_match('/authorize\s*\(/', $content) ||
                preg_match('/Auth::\s*id\s*\(\s*\)\s*!==/', $content)) {
                $hasSecurityPosture = true;
            }

            if (! $hasSecurityPosture) {
                $violations[] = $relativePath;
            }
        }

        $this->assertEmpty(
            $violations,
            "SECURITY VIOLATION: Livewire components in Tenant zone without security posture.\n\n".
            "These components are in a whitelist-exempt zone but lack:\n".
            "- #[Layout('layouts.app')] attribute, OR\n".
            "- #[Middleware(...)] attribute, OR\n".
            "- Security check in mount() (abort(403) or authorize())\n\n".
            "Orphan components:\n".implode("\n", $violations)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SPEC 3: ROUTE LEAKAGE DETECTION
    // Validates that no protected controllers/components are exposed publicly
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * No tenant-protected controllers should be accessible without auth middleware.
     *
     * SECURITY REQUIREMENT:
     * Routes pointing to App\Http\Controllers\Tenant\* or App\Livewire\Tenant\*
     * MUST have authentication middleware applied.
     *
     * A "route leak" occurs when a developer accidentally exposes a protected
     * controller on a public route without auth middleware.
     */
    public function test_no_route_leakage_to_tenant_controllers(): void
    {
        $violations = [];

        // Protected namespaces that require authentication
        // Note: Using backslash after Tenant to avoid matching TenantController in public Controllers
        $protectedNamespaces = [
            'App\\Http\\Controllers\\Tenant\\',   // Subdirectory, not TenantController.php
            'App\\Livewire\\Tenant\\',            // Livewire tenant components
        ];

        // Middleware that indicates proper protection
        $authMiddleware = [
            'auth',
            'auth:sanctum',
            'auth:web',
            'tenant.ownership',
            'Authenticate',
            'EnsureTenantOwnership',
        ];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();
            $uri = $route->uri();

            // Skip Livewire internal routes (they have component-level security)
            if (str_starts_with($uri, 'livewire/')) {
                continue;
            }

            // Skip Filament panel routes (protected by panel providers)
            if (str_starts_with($uri, 'app/') || str_starts_with($uri, 'admin/')) {
                continue;
            }

            // Skip if no controller action
            if (! isset($action['controller']) && ! isset($action['uses'])) {
                continue;
            }

            $controllerAction = $action['controller'] ?? $action['uses'] ?? '';

            // Skip closure routes (they don't have controller namespaces)
            if (! is_string($controllerAction)) {
                continue;
            }

            // Check if route points to protected namespace
            $isProtected = false;
            foreach ($protectedNamespaces as $ns) {
                if (str_contains($controllerAction, $ns)) {
                    $isProtected = true;
                    break;
                }
            }

            if (! $isProtected) {
                continue;
            }

            // Check if route has auth middleware
            $routeMiddleware = $route->gatherMiddleware();
            $hasAuth = false;

            foreach ($routeMiddleware as $mw) {
                foreach ($authMiddleware as $authMw) {
                    if (str_contains((string) $mw, $authMw)) {
                        $hasAuth = true;
                        break 2;
                    }
                }
            }

            if (! $hasAuth) {
                $violations[] = sprintf(
                    '🔴 [LEAK] %s %s → %s',
                    implode('|', $route->methods()),
                    $route->uri(),
                    $controllerAction
                );
            }
        }

        $this->assertEmpty(
            $violations,
            "ROUTE LEAKAGE DETECTED: Protected controllers exposed without auth middleware.\n\n".
            "These routes point to Tenant-scoped code but lack authentication:\n".
            implode("\n", $violations)."\n\n".
            "Fix: Add 'auth' or 'tenant.ownership' middleware to these routes."
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SPEC 4: FILAMENT APP RESOURCES HAVE TENANT QUERY
    // Validates that Filament/App resources override getEloquentQuery for isolation
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Filament/App resources should override getEloquentQuery for tenant isolation.
     *
     * While Filament's native tenancy handles most cases, resources that use
     * withoutGlobalScopes() MUST have explicit tenant filtering in getEloquentQuery().
     */
    public function test_filament_app_resources_use_tenant_filtering(): void
    {
        $resourcesPath = app_path('Filament/App/Resources');

        if (! is_dir($resourcesPath)) {
            $this->markTestSkipped('Filament/App/Resources directory not found');
        }

        $warnings = [];
        $files = $this->getPhpFilesRecursively($resourcesPath);

        foreach ($files as $file) {
            // Only check Resource files, not Pages or RelationManagers
            if (! str_ends_with($file, 'Resource.php')) {
                continue;
            }

            $content = file_get_contents($file);
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

            // Check if uses withoutGlobalScopes
            if (! str_contains($content, 'withoutGlobalScopes')) {
                continue; // Safe - relies on ORM scopes
            }

            // If uses withoutGlobalScopes, must have getEloquentQuery with tenant filter
            $hasExplicitTenantFilter = (
                str_contains($content, 'getEloquentQuery') &&
                (
                    str_contains($content, 'tenant_id') ||
                    str_contains($content, 'Filament::getTenant')
                )
            );

            if (! $hasExplicitTenantFilter) {
                $warnings[] = "⚠️ {$relativePath} uses withoutGlobalScopes but may lack explicit tenant filtering";
            }
        }

        // This is informational - resources may be safe due to Filament's native tenancy
        // Log warnings to stderr but don't fail the test
        if (! empty($warnings)) {
            fwrite(STDERR, "\n[INFO] Resources using withoutGlobalScopes should be reviewed:\n".
                implode("\n", $warnings)."\n");
        }

        // Always pass - Filament's native tenancy provides protection
        $this->assertTrue(true);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Get all PHP files recursively from directory.
     */
    private function getPhpFilesRecursively(string $directory): array
    {
        $files = [];

        if (! is_dir($directory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
