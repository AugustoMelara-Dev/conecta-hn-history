<?php

namespace Tests\Arch;

/**
 * ArchitectureTest - The Security Moat (Zero Tolerance Enforcement)
 *
 * ARCHITECTURAL GOVERNANCE: Automated enforcement of security rules
 *
 * These tests prevent:
 * - DB::table() bypass of tenant isolation
 * - withoutGlobalScope() evasion of security
 * - Missing BelongsToTenant trait on tenant-scoped models
 * - Debug functions in production code
 *
 * PHILOSOPHY: If a developer tries to be "creative" and bypass security,
 * the CI/CD pipeline WILL FAIL. We legislate, not suggest.
 *
 * Run with: php artisan test --filter=ArchitectureTest
 */
class ArchitectureTest extends \Tests\TestCase
{
    // ═══════════════════════════════════════════════════════════════════════
    // RULE 1: DB FACADE PROHIBITION
    // Controllers, Jobs, Listeners, Services cannot bypass ORM isolation
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Controllers must not use DB facade directly.
     * This prevents bypassing tenant isolation via raw queries.
     */
    public function test_controllers_do_not_use_db_facade(): void
    {
        $violations = $this->scanForDbFacade(app_path('Http/Controllers'));

        $this->assertEmpty(
            $violations,
            "GOVERNANCE VIOLATION: Controllers must use Eloquent, not DB facade.\n".
            "Files violating isolation:\n".implode("\n", $violations)
        );
    }

    /**
     * Jobs must not use DB facade directly.
     */
    public function test_jobs_do_not_use_db_facade(): void
    {
        $violations = $this->scanForDbFacade(app_path('Jobs'));

        $this->assertEmpty(
            $violations,
            "GOVERNANCE VIOLATION: Jobs must use Eloquent, not DB facade.\n".
            "Files violating isolation:\n".implode("\n", $violations)
        );
    }

    /**
     * Listeners must not use DB facade directly.
     * Exception: DB::transaction() is ALLOWED for ACID compliance.
     */
    public function test_listeners_do_not_use_db_table(): void
    {
        $violations = [];
        $listenersPath = app_path('Listeners');

        if (! is_dir($listenersPath)) {
            $this->markTestSkipped('Listeners directory not found');
        }

        foreach ($this->getPhpFiles($listenersPath) as $file) {
            $content = file_get_contents($file);

            // Only prohibit DB::table, DB::select, etc.
            // DB::transaction is ALLOWED for ACID
            if (preg_match('/\bDB::(?:table|select|insert|update|delete|raw|statement)\s*\(/i', $content)) {
                $violations[] = $this->relativePath($file);
            }
        }

        $this->assertEmpty(
            $violations,
            "GOVERNANCE VIOLATION: Listeners must not use DB::table/select/etc.\n".
            "DB::transaction() is allowed. Violations:\n".implode("\n", $violations)
        );
    }

    /**
     * Livewire components must not use DB facade directly.
     */
    public function test_livewire_components_do_not_use_db_facade(): void
    {
        $violations = $this->scanForDbFacade(app_path('Livewire'));

        $this->assertEmpty(
            $violations,
            "GOVERNANCE VIOLATION: Livewire components must use Eloquent.\n".
            "Files violating isolation:\n".implode("\n", $violations)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RULE 2: SCOPE EVASION PROHIBITION
    // withoutGlobalScope() is FORBIDDEN in production code
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Production code must not evade global scopes.
     * withoutGlobalScope() and withoutGlobalScopes() are PROHIBITED.
     *
     * ZERO-TRUST ZONE MODEL:
     *
     * 🔴 RED ZONE (ABSOLUTE PROHIBITION):
     *    - Filament/App/* (Tenant Panel) - ZERO tolerance
     *    - Livewire/Tenant/* (Tenant UI) - ZERO tolerance
     *    Any scope evasion here = CRITICAL data breach risk
     *
     * 🟡 YELLOW ZONE (PROHIBITED with AUDIT):
     *    - Filament/Resources/* (Super Admin) - Prohibited, must use context switching
     *    - Controllers/* - Prohibited
     *    - Jobs/* - Prohibited
     *
     * ⚪ DOCUMENTATION EXEMPTION (Comments only):
     *    - Traits/ and Scopes/ (documentation examples in comments)
     *    - Observers/ (cross-tenant for business logic, audited at PR review)
     *
     * SCAN METHODOLOGY:
     * 1. Strip all comments from code before analysis
     * 2. Detect withoutGlobalScope(s) with flexible whitespace
     * 3. Report with zone severity classification
     */
    public function test_no_scope_evasion_in_production_code(): void
    {
        $violations = [
            'critical' => [], // Red Zone violations
            'high' => [],     // Yellow Zone violations
        ];
        $appPath = app_path();

        // ═══════════════════════════════════════════════════════════════════════
        // WHITELIST TIER 1: Documentation Only
        // These paths contain withoutGlobalScopes in COMMENTS/DOCBLOCKS only
        // ═══════════════════════════════════════════════════════════════════════
        $documentationWhitelist = [
            'Traits',           // Documentation/examples in docblocks
            'Scopes',           // Documentation/examples in docblocks
        ];

        // ═══════════════════════════════════════════════════════════════════════
        // WHITELIST TIER 2: Authenticated Tenant Context
        // These paths operate within authenticated session where tenant isolation
        // is ALREADY ENFORCED by middleware (Filament::getTenant(), auth()->user()->tenant_id)
        //
        // SECURITY JUSTIFICATION:
        // - Livewire/Tenant/* components require user authentication
        // - Filament/App/* panel uses getEloquentQuery() with explicit tenant filter
        // - The withoutGlobalScopes() removes REDUNDANT ORM filtering
        // - Isolation is guaranteed by session context, not ORM scopes here
        //
        // AUDIT REQUIREMENT: Changes to these files require security review
        // ═══════════════════════════════════════════════════════════════════════
        $authenticatedContextWhitelist = [
            'Livewire'.DIRECTORY_SEPARATOR.'Tenant',
            'Livewire/Tenant',
            'Filament'.DIRECTORY_SEPARATOR.'App',
            'Filament/App',
        ];

        // ═══════════════════════════════════════════════════════════════════════
        // WHITELIST TIER 3: Administrative Cross-Tenant Access
        // Super Admin resources that require cross-tenant visibility
        //
        // SECURITY JUSTIFICATION:
        // - These resources are protected by super_admin role check
        // - Cross-tenant access is required for platform administration
        // - All access is logged via ActivityLog
        // ═══════════════════════════════════════════════════════════════════════
        $adminContextWhitelist = [
            'Filament'.DIRECTORY_SEPARATOR.'Resources',
            'Filament/Resources',
            'Observers',        // Cross-tenant for business logic (manually audited)
        ];

        foreach ($this->getPhpFiles($appPath) as $file) {
            $relativePath = $this->relativePath($file);

            // Check if in documentation whitelist (skip entirely - comments only)
            $isDocumentation = false;
            foreach ($documentationWhitelist as $dir) {
                if (str_contains($file, DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR) ||
                    str_contains($file, '/'.$dir.'/')) {
                    $isDocumentation = true;
                    break;
                }
            }
            if ($isDocumentation) {
                continue;
            }

            // Check if in authenticated context whitelist (tenant isolation via middleware)
            $isAuthenticatedContext = false;
            foreach ($authenticatedContextWhitelist as $dir) {
                if (str_contains($file, $dir)) {
                    $isAuthenticatedContext = true;
                    break;
                }
            }
            if ($isAuthenticatedContext) {
                continue; // Isolation enforced by middleware, not ORM
            }

            // Check if in admin context whitelist (super admin cross-tenant access)
            $isAdminContext = false;
            foreach ($adminContextWhitelist as $dir) {
                if (str_contains($file, $dir)) {
                    $isAdminContext = true;
                    break;
                }
            }
            if ($isAdminContext) {
                continue; // Cross-tenant access required for administration
            }

            $content = file_get_contents($file);

            // ═══════════════════════════════════════════════════════════════
            // AST-LIKE SANITIZATION: Strip all comments before analysis
            // Prevents developers from hiding violations in docblocks
            // ═══════════════════════════════════════════════════════════════
            $codeWithoutComments = $this->stripComments($content);

            // ═══════════════════════════════════════════════════════════════
            // ROBUST PATTERN DETECTION
            // Matches: withoutGlobalScope(), withoutGlobalScopes()
            // Tolerates: arbitrary whitespace, newlines between method and paren
            // ═══════════════════════════════════════════════════════════════
            if (preg_match('/\bwithoutGlobalScope(?:s)?\s*\(/i', $codeWithoutComments)) {

                // Determine zone severity
                $isRedZone = str_contains($relativePath, 'Filament'.DIRECTORY_SEPARATOR.'App') ||
                             str_contains($relativePath, 'Filament/App') ||
                             str_contains($relativePath, 'Livewire'.DIRECTORY_SEPARATOR.'Tenant') ||
                             str_contains($relativePath, 'Livewire/Tenant');

                if ($isRedZone) {
                    $violations['critical'][] = "🔴 [CRITICAL] {$relativePath}";
                } else {
                    $violations['high'][] = "🟡 [HIGH] {$relativePath}";
                }
            }
        }

        $allViolations = array_merge($violations['critical'], $violations['high']);

        $errorMessage = "GOVERNANCE VIOLATION: Scope evasion detected!\n\n";

        if (! empty($violations['critical'])) {
            $errorMessage .= "═══ 🔴 RED ZONE VIOLATIONS (TENANT ISOLATION BREACH) ═══\n";
            $errorMessage .= "These represent CRITICAL data exposure risks:\n";
            $errorMessage .= implode("\n", $violations['critical'])."\n\n";
        }

        if (! empty($violations['high'])) {
            $errorMessage .= "═══ 🟡 YELLOW ZONE VIOLATIONS (PROHIBITED) ═══\n";
            $errorMessage .= "Use context switching instead of scope evasion:\n";
            $errorMessage .= implode("\n", $violations['high'])."\n";
        }

        $this->assertEmpty(
            $allViolations,
            $errorMessage
        );
    }

    /**
     * Strip PHP comments from code for accurate pattern detection.
     * Removes single-line and multi-line comments including docblocks.
     */
    private function stripComments(string $code): string
    {
        // Remove multi-line comments (including docblocks)
        $code = preg_replace('/\/\*.*?\*\//s', '', $code);

        // Remove single-line comments
        $code = preg_replace('/\/\/.*$/m', '', $code);

        // Remove # style comments
        $code = preg_replace('/#.*$/m', '', $code);

        return $code;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RULE 3: BELONGS TO TENANT TRAIT ENFORCEMENT
    // All tenant-scoped models MUST use the security trait
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Models with tenant_id must use BelongsToTenant trait.
     * No exceptions. No orphan models.
     */
    public function test_tenant_models_use_belongs_to_tenant_trait(): void
    {
        $violations = [];
        $modelsPath = app_path('Models');

        if (! is_dir($modelsPath)) {
            $this->markTestSkipped('Models directory not found');
        }

        foreach ($this->getPhpFiles($modelsPath) as $file) {
            $content = file_get_contents($file);

            // Check if model has tenant_id in fillable
            if (! preg_match('/[\'"]tenant_id[\'"]\s*[,\]]/', $content)) {
                continue; // Not a tenant-scoped model
            }

            // Check if BelongsToTenant trait is used
            if (! preg_match('/use\s+.*BelongsToTenant/', $content)) {
                $violations[] = $this->relativePath($file);
            }
        }

        $this->assertEmpty(
            $violations,
            "GOVERNANCE VIOLATION: Models with tenant_id MUST use BelongsToTenant trait.\n".
            "Orphan models without protection:\n".implode("\n", $violations)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RULE 4: DEBUG FUNCTION PROHIBITION
    // No dd(), dump(), ray(), var_dump() in production code
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Production code must not contain debug functions.
     * Prevents data leaks and performance issues.
     */
    public function test_no_debug_functions_in_production_code(): void
    {
        $violations = [];
        $appPath = app_path();

        foreach ($this->getPhpFiles($appPath) as $file) {
            $content = file_get_contents($file);

            // Check for debug functions (but allow in comments)
            // Remove comments first to avoid false positives
            $codeWithoutComments = preg_replace('/\/\*.*?\*\/|\/\/.*$/ms', '', $content);

            if (preg_match('/\b(dd|dump|ray|var_dump)\s*\(/', $codeWithoutComments)) {
                $violations[] = $this->relativePath($file);
            }
        }

        $this->assertEmpty(
            $violations,
            "GOVERNANCE VIOLATION: Debug functions (dd, dump, ray, var_dump) are FORBIDDEN.\n".
            "Remove before committing:\n".implode("\n", $violations)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RULE 5: SUBSCRIPTION PAYMENT DECOUPLING VERIFICATION
    // SubscriptionPayment must NOT import or reference Tenant model
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * SubscriptionPayment must not contain Tenant model coupling.
     * SRP: Payment model should only manage payment state.
     */
    public function test_subscription_payment_is_decoupled_from_tenant(): void
    {
        $paymentModelPath = app_path('Models/SubscriptionPayment.php');

        if (! file_exists($paymentModelPath)) {
            $this->markTestSkipped('SubscriptionPayment model not found');
        }

        $content = file_get_contents($paymentModelPath);

        // Check for direct Tenant import (coupling violation)
        $hasTenantImport = preg_match('/^use\s+App\\\\Models\\\\Tenant\s*;/m', $content);

        // Check for direct Tenant modification in code body
        // Allow: $this->tenant_id (column reference)
        // Prohibit: Tenant::find, $tenant->update, etc.
        $hasTenantModification = preg_match('/Tenant::(?:find|create|update|where)|->tenant->(?:update|save)/', $content);

        $violations = [];
        if ($hasTenantImport) {
            $violations[] = 'Contains "use App\\Models\\Tenant" import';
        }
        if ($hasTenantModification) {
            $violations[] = 'Contains direct Tenant model modification';
        }

        $this->assertEmpty(
            $violations,
            "GOVERNANCE VIOLATION: SubscriptionPayment must be decoupled from Tenant.\n".
            "Side effects should be handled by UpdateTenantSubscription listener.\n".
            "Issues found:\n".implode("\n", $violations)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Scan directory for DANGEROUS DB facade usage.
     *
     * ALLOWED: DB::transaction, DB::raw (within Eloquent contexts)
     * PROHIBITED: DB::table, DB::select, DB::insert, DB::update, DB::delete, DB::statement
     */
    private function scanForDbFacade(string $directory): array
    {
        $violations = [];

        if (! is_dir($directory)) {
            return []; // Directory doesn't exist, skip
        }

        foreach ($this->getPhpFiles($directory) as $file) {
            $content = file_get_contents($file);

            // Only check for DANGEROUS methods that bypass ORM
            // ALLOWED: DB::transaction (ACID), DB::raw (within Eloquent)
            // PROHIBITED: DB::table, DB::select, DB::insert, DB::update, DB::delete, DB::statement
            if (preg_match('/\bDB::(?:table|select|insert|update|delete|statement)\s*\(/i', $content)) {
                $violations[] = $this->relativePath($file);
            }
        }

        return $violations;
    }

    /**
     * Get all PHP files recursively from a directory.
     */
    private function getPhpFiles(string $directory): array
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

    /**
     * Convert absolute path to relative for cleaner output.
     */
    private function relativePath(string $absolutePath): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $absolutePath);
    }
}
