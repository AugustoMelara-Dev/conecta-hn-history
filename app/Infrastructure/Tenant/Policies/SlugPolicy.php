<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Policies;

use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * SlugPolicy - Domain Policy for Tenant Slug Validation
 *
 * Extracted from App\Services\TenantRegistrationService.
 *
 * PURE METHODS: isReservedSlug() — no I/O, no framework coupling.
 * INFRASTRUCTURE DEBT: isSlugAvailable() and generateSuggestions() still
 *   perform Eloquent queries. These will be decoupled via a
 *   SlugAvailabilityChecker port in a subsequent W-02 remediation pass.
 */
final class SlugPolicy
{
    // ═══════════════════════════════════════════════════════════════════════
    // RESERVED SLUGS FIREWALL
    // ═══════════════════════════════════════════════════════════════════════
    private const RESERVED_SLUGS = [
        // System
        'admin', 'administrator', 'dashboard', 'panel', 'app', 'api', 'system',
        // Auth
        'login', 'logout', 'register', 'registrar', 'signup', 'signin', 'auth',
        // Critical routes
        'directorio', 'directory', 'explorar', 'explore', 'buscar', 'search',
        'categoria', 'category', 'ciudad', 'city', 'ciudades', 'cities',
        // Support
        'soporte', 'support', 'help', 'ayuda', 'contacto', 'contact',
        // Security
        'root', 'superadmin', 'moderator', 'mod', 'staff', 'team',
        // Brand
        'conecta', 'conectahn', 'conecta-hn', 'oficial', 'official',
        // Utilities
        'track', 'tracking', 'webhook', 'webhooks', 'callback', 'oauth',
        'payment', 'payments', 'pago', 'pagos', 'checkout', 'billing',
        // Legal
        'terms', 'privacy', 'legal', 'about', 'nosotros', 'acerca',
        // Impersonation
        'impersonate', 'shadow', 'sudo',
        // Storage
        'storage', 'uploads', 'files', 'images', 'assets', 'public',
        // Testing
        'test', 'demo', 'example', 'sample', 'prueba',
    ];

    /**
     * Check if slug is reserved by system. PURE — no I/O.
     */
    public static function isReservedSlug(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED_SLUGS, true);
    }

    /**
     * Check slug availability (reserved + database).
     *
     * TODO (W-02): Inject SlugAvailabilityCheckerInterface to remove Eloquent coupling.
     */
    public static function isSlugAvailable(string $slug): bool
    {
        if (self::isReservedSlug($slug)) {
            return false;
        }

        return ! Tenant::where('slug', $slug)->exists();
    }

    /**
     * Generate smart suggestions for taken slugs.
     *
     * TODO (W-02): Receive availability checker via parameter to remove Eloquent coupling.
     *
     * @return array<string>
     */
    public static function generateSuggestions(string $baseSlug, int $limit = 3): array
    {
        $candidates = [
            $baseSlug.'-hn',
            $baseSlug.'-'.date('Y'),
            $baseSlug.'-oficial',
            $baseSlug.'-'.Str::random(3),
            $baseSlug.'-'.now()->format('md'),
        ];

        $suggestions = [];

        foreach ($candidates as $candidate) {
            if (self::isSlugAvailable($candidate)) {
                $suggestions[] = $candidate;
            }

            if (count($suggestions) >= $limit) {
                break;
            }
        }

        return $suggestions;
    }
}
