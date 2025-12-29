<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Announcement;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

/**
 * HandleInertiaRequests - Global State Hydration Middleware (PRODUCTION v10)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CONFIG-DRIVEN: Route patterns loaded from config/tenancy.php          ║
 * ║  OPEN/CLOSED: Adding routes doesn't require code changes               ║
 * ║  TENANT VALIDATED: instanceof + exists check                           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class HandleInertiaRequests extends Middleware
{
    private const ANNOUNCEMENTS_CACHE_TTL_KEY = 'cache.ttl.announcements';
    private const ANNOUNCEMENTS_CACHE_TTL_DEFAULT = 300;
    private const ANNOUNCEMENTS_CACHE_TTL_MAX = 600;
    private const HEX_COLOR_PATTERN = '/^#[0-9A-Fa-f]{6}$/';
    private const DEFAULT_BRAND_COLOR = '#111827';
    private const LOGO_PATH_PREFIX = 'tenants/';
    private const ALLOWED_LOGO_EXTENSIONS = '/\.(png|jpg|jpeg|webp)$/i';

    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],

            'tenant' => fn () => $this->getGuardedTenantData($request),
            'announcements' => fn () => $this->getGuardedAnnouncements($request),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    private function isWithinTenantContext(Request $request): bool
    {
        // Config-driven: Routes loaded from config/tenancy.php
        $contextRoutes = config('tenancy.context_routes', []);

        foreach ($contextRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function getGuardedTenantData(Request $request): ?array
    {
        if (!$this->isWithinTenantContext($request)) {
            return null;
        }

        return $this->getTenantData($request);
    }

    private function getGuardedAnnouncements(Request $request): array
    {
        if (!$this->isWithinTenantContext($request)) {
            return [];
        }

        return $this->getCachedAnnouncements($request);
    }

    /**
     * Get deterministic cache version with ZERO DISK I/O on hot path.
     *
     * ARCHITECTURE:
     * ─────────────────────────────────────────────────────────────────────────────
     * 1. Manual override via config (instant return)
     * 2. Memory-cached file mtime (eliminates IOPS saturation)
     * 3. Static fallback (fail-safe)
     *
     * INVALIDATION: Standard deploy hook (php artisan cache:clear)
     *
     * WHY: In high-concurrency environments (AWS EFS, NFS, Docker volumes),
     * synchronous stat/filemtime calls on every request saturate IOPS.
     */
    private function getCacheVersion(): string
    {
        // Priority 1: Manual version override (instant return, no cache lookup)
        $configVersion = config('app.version');
        if ($configVersion && $configVersion !== 'auto') {
            return $configVersion;
        }

        // Priority 2: Memory-cached file-based version (ZERO DISK I/O on hot path)
        // TTL: 60 minutes. Invalidated by deploy hook: php artisan cache:clear
        return Cache::remember('app_build_version', 3600, function (): string {
            $versionFiles = [
                public_path('build/manifest.json'),   // Vite production manifest
                base_path('vite.config.js'),           // Vite config
                public_path('mix-manifest.json'),      // Laravel Mix fallback
            ];

            foreach ($versionFiles as $file) {
                try {
                    if (file_exists($file)) {
                        $mtime = filemtime($file);
                        if ($mtime !== false) {
                            return 'v' . $mtime;
                        }
                    }
                } catch (\Throwable) {
                    // Fail silently - continue to next file or fallback
                    continue;
                }
            }

            // Priority 3: Static fallback (fail-safe)
            return '1.0.0';
        });
    }

    protected function getCachedAnnouncements(Request $request): array
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            return [];
        }

        $cacheKey = sprintf(
            '%s:business:%d:announcements:%s',
            app()->environment(),
            $tenant->id,
            $this->getCacheVersion()
        );

        $ttl = min(
            (int) config(self::ANNOUNCEMENTS_CACHE_TTL_KEY, self::ANNOUNCEMENTS_CACHE_TTL_DEFAULT),
            self::ANNOUNCEMENTS_CACHE_TTL_MAX
        );

        return Cache::remember($cacheKey, $ttl, function () use ($tenant) {
            // DRY: Reuse Announcement::active() scope instead of duplicating logic
            // CONFIG-DRIVEN: Use tenancy.sharing.max_announcements for limit
            $announcements = Announcement::query()
                ->where('business_id', $tenant->id)
                ->active()
                ->orderBy('created_at', 'desc')
                ->limit(config('tenancy.sharing.max_announcements', 5))
                ->get();

            return $announcements->map(function (Announcement $announcement): array {
                // NOTE: content_html is sanitized here AND in frontend (double belt)
                // Backend sanitization via Announcement::getContentHtmlAttribute()
                return [
                    'id' => (int) $announcement->id,
                    'type' => (string) $announcement->type,
                    'content_html' => (string) $announcement->content_html,
                    'type_color' => (string) $announcement->type_color,
                ];
            })->all();
        });
    }

    /**
     * Resolve tenant with FULL validation.
     *
     * VALIDATES:
     * - Container binding exists
     * - Value is instanceof Tenant
     * - Record exists in database
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        if (!app()->has('currentTenant')) {
            return null;
        }

        $tenant = app('currentTenant');

        // TYPE VALIDATION: Must be Tenant instance
        if (!$tenant instanceof Tenant) {
            return null;
        }

        // EXISTENCE VALIDATION: Must exist in database
        if (!$tenant->exists) {
            return null;
        }

        return $tenant;
    }

    protected function getTenantData(Request $request): ?array
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return null;
        }

        return [
            'id' => (int) $tenant->id,
            'name' => (string) $tenant->name,
            'slug' => (string) $tenant->slug,
            'logo_url' => $this->sanitizeLogoPath($tenant->logo_path),

            'whatsapp' => $tenant->whatsapp ? (string) $tenant->whatsapp : null,
            'whatsapp_number' => $tenant->whatsapp_number ? (string) $tenant->whatsapp_number : null,
            'phone' => $tenant->phone ? (string) $tenant->phone : null,

            'is_pro' => (bool) ($tenant->is_pro ?? false),

            'primary_color' => $this->sanitizeHexColor($tenant->primary_color),

            'settings' => [
                'delivery_enabled' => (bool) ($tenant->delivery_enabled ?? true),
                'pickup_enabled' => (bool) ($tenant->pickup_enabled ?? true),
                'minimum_order' => (float) ($tenant->minimum_order ?? 0),
            ],
        ];
    }

    private function sanitizeHexColor(?string $color): string
    {
        if ($color === null || !preg_match(self::HEX_COLOR_PATTERN, $color)) {
            return self::DEFAULT_BRAND_COLOR;
        }

        return $color;
    }

    private function sanitizeLogoPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (!str_starts_with($path, self::LOGO_PATH_PREFIX)) {
            return null;
        }

        if (str_contains($path, '..') || str_contains($path, '//')) {
            return null;
        }

        if (!preg_match(self::ALLOWED_LOGO_EXTENSIONS, $path)) {
            return null;
        }

        if (!Storage::exists($path)) {
            return null;
        }

        return Storage::url($path);
    }

    public static function invalidateAnnouncementsCache(int $tenantId): void
    {
        $version = config('app.version', date('Y.m.d.H'));
        $cacheKey = sprintf(
            '%s:business:%d:announcements:%s',
            app()->environment(),
            $tenantId,
            $version
        );
        Cache::forget($cacheKey);
    }
}
