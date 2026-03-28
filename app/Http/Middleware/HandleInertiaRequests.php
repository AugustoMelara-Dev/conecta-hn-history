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
 * HandleInertiaRequests - Global State Hydration Middleware (REFACTORED)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MODULAR: Logic extracted to private methods for testability            ║
 * ║  CONFIG-DRIVEN: Route patterns loaded from config/tenancy.php           ║
 * ║  TENANT VALIDATED: instanceof + exists check                            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class HandleInertiaRequests extends Middleware
{
    private const ANNOUNCEMENTS_CACHE_TTL = 300;

    private const HEX_COLOR_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    private const DEFAULT_BRAND_COLOR = '#111827';

    private const LOGO_PATH_PREFIX = 'tenants/';

    private const ALLOWED_LOGO_EXTENSIONS = '/\.(png|jpg|jpeg|webp)$/i';

    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Share data with all Inertia responses.
     * REFACTORED: Composed from modular private methods.
     */
    public function share(Request $request): array
    {
        return array_merge(
            parent::share($request),
            $this->shareAuthData($request),
            $this->shareTenantData($request),
            $this->shareFlashMessages($request)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MODULAR SHARE METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Share authenticated user data.
     */
    protected function shareAuthData(Request $request): array
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        return [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role instanceof \BackedEnum ? $user->role->value : $user->role,
                ] : null,
                'tenant' => $tenant ? [
                    'id' => (string) $tenant->getKey(),
                    'name' => (string) $tenant->name,
                    'slug' => (string) $tenant->slug,
                ] : null,
            ],
        ];
    }

    /**
     * Share tenant context data (lazy-loaded).
     */
    protected function shareTenantData(Request $request): array
    {
        return [
            'tenant' => fn () => $this->getGuardedTenantData($request),
            'announcements' => fn () => $this->getGuardedAnnouncements($request),
        ];
    }

    /**
     * Share flash messages from session.
     */
    protected function shareFlashMessages(Request $request): array
    {
        return [
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TENANT CONTEXT RESOLUTION
    // ═══════════════════════════════════════════════════════════════════════

    private function isWithinTenantContext(Request $request): bool
    {
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
        if (! $this->isWithinTenantContext($request)) {
            return null;
        }

        return $this->getTenantData($request);
    }

    private function getGuardedAnnouncements(Request $request): array
    {
        if (! $this->isWithinTenantContext($request)) {
            return [];
        }

        return $this->getCachedAnnouncements($request);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TENANT DATA TRANSFORMATION
    // ═══════════════════════════════════════════════════════════════════════

    protected function resolveTenant(Request $request): ?Tenant
    {
        if (! app()->has('currentTenant')) {
            return null;
        }

        $tenant = app('currentTenant');

        if (! $tenant instanceof Tenant) {
            return null;
        }

        if (! $tenant->exists) {
            return null;
        }

        return $tenant;
    }

    protected function getTenantData(Request $request): ?array
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            return null;
        }

        $planType = (string) ($tenant->getRawOriginal('plan_type') ?: ($tenant->getAttributes()['plan_type'] ?? Tenant::PLAN_SEMILLA));

        return [
            'plan_type' => $planType,
            'id' => (string) $tenant->id,
            'name' => (string) $tenant->name,
            'slug' => (string) $tenant->slug,
            'logo_url' => $this->sanitizeLogoPath($tenant->logo_path),
            'whatsapp' => $tenant->whatsapp_number ? (string) $tenant->whatsapp_number : null,
            'whatsapp_number' => $tenant->whatsapp_number ? (string) $tenant->whatsapp_number : null,
            'phone' => $tenant->phone ? (string) $tenant->phone : null,
            'is_paid_plan' => in_array($planType, ['pro', Tenant::PLAN_CRECIMIENTO, Tenant::PLAN_COSECHA, 'enterprise'], true),
            'primary_color' => $this->sanitizeHexColor($tenant->primary_color),
            'settings' => [
                'delivery_enabled' => (bool) ($tenant->delivery_enabled ?? true),
                'pickup_enabled' => (bool) ($tenant->pickup_enabled ?? true),
                'minimum_order' => (float) ($tenant->minimum_order ?? 0),
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ANNOUNCEMENTS (CACHED)
    // ═══════════════════════════════════════════════════════════════════════

    protected function getCachedAnnouncements(Request $request): array
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            return [];
        }

        $cacheKey = sprintf(
            '%s:business:%s:announcements:%s',
            app()->environment(),
            (string) $tenant->id,
            $this->getCacheVersion()
        );

        return Cache::remember($cacheKey, self::ANNOUNCEMENTS_CACHE_TTL, function () use ($tenant) {
            return Announcement::query()
                ->where('business_id', $tenant->id)
                ->active()
                ->orderBy('created_at', 'desc')
                ->limit(config('tenancy.sharing.max_announcements', 5))
                ->get()
                ->map(fn (Announcement $a) => [
                    'id' => (int) $a->id,
                    'type' => (string) $a->type,
                    'content_html' => (string) $a->content_html,
                    'type_color' => (string) $a->type_color,
                ])
                ->all();
        });
    }

    private function getCacheVersion(): string
    {
        $configVersion = config('app.version');
        if ($configVersion && $configVersion !== 'auto') {
            return $configVersion;
        }

        return Cache::remember('app_build_version', 3600, function (): string {
            $file = public_path('build/manifest.json');
            if (file_exists($file) && ($mtime = filemtime($file)) !== false) {
                return 'v'.$mtime;
            }

            return '1.0.0';
        });
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SANITIZATION
    // ═══════════════════════════════════════════════════════════════════════

    private function sanitizeHexColor(?string $color): string
    {
        if ($color === null || ! preg_match(self::HEX_COLOR_PATTERN, $color)) {
            return self::DEFAULT_BRAND_COLOR;
        }

        return $color;
    }

    private function sanitizeLogoPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (! str_starts_with($path, self::LOGO_PATH_PREFIX)) {
            return null;
        }

        if (str_contains($path, '..') || str_contains($path, '//')) {
            return null;
        }

        if (! preg_match(self::ALLOWED_LOGO_EXTENSIONS, $path)) {
            return null;
        }

        if (! Storage::exists($path)) {
            return null;
        }

        return Storage::url($path);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CACHE INVALIDATION (STATIC HELPER)
    // ═══════════════════════════════════════════════════════════════════════

    public static function invalidateAnnouncementsCache(int|string $tenantId): void
    {
        $version = config('app.version', date('Y.m.d.H'));
        $cacheKey = sprintf(
            '%s:business:%s:announcements:%s',
            app()->environment(),
            (string) $tenantId,
            $version
        );
        Cache::forget($cacheKey);
    }
}
