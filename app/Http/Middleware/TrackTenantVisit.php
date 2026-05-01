<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantVisit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TrackTenantVisit - First-Party Analytics Middleware
 *
 * PRIVACY-COMPLIANT TRACKING:
 * - No persistent cookies
 * - IP addresses hashed with daily salt
 * - Same visitor = same hash for 24 hours only
 *
 * DEDUPLICATION:
 * - Session fingerprint checked before write
 * - 5 page refreshes = 1 visit recorded
 *
 * PERFORMANCE:
 * - Uses terminate() for async processing
 * - Doesn't block HTTP response
 */
class TrackTenantVisit
{
    /**
     * Cache for tenant data during request lifecycle.
     */
    protected ?Tenant $tenant = null;

    protected ?Request $request = null;

    /**
     * Handle an incoming request.
     *
     * Just tag the request for tracking, actual work happens in terminate()
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Store request for terminate() method
        $this->request = $request;

        $this->tenant = $this->resolveTenantFromRoute($request->route('tenant'));

        return $next($request);
    }

    /**
     * Perform tasks after the response has been sent to the browser.
     *
     * This method is called AFTER the response is sent, so it doesn't
     * impact perceived page load time.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Only track successful pages
        if ($response->getStatusCode() !== 200) {
            return;
        }

        // Need a tenant to track
        if (! $this->tenant) {
            return;
        }

        // Skip bots from visit count (but still let them through for SEO)
        $userAgent = $request->userAgent() ?? '';
        if ($this->isBot($userAgent)) {
            return;
        }

        $this->recordVisit($request);
    }

    /**
     * Record a visit with deduplication.
     */
    protected function recordVisit(Request $request): void
    {
        try {
            $ip = $request->ip() ?? '0.0.0.0';
            $userAgent = $request->userAgent() ?? 'Unknown';
            $tenantId = $this->tenant->id;

            // Generate session fingerprint
            $sessionHash = TenantVisit::generateSessionHash($ip, $userAgent, $tenantId);

            // ═══════════════════════════════════════════════════════════════
            // DEDUPLICATION CHECK: Already tracked this session today?
            // ═══════════════════════════════════════════════════════════════
            $existsToday = TenantVisit::query()
                ->where('tenant_id', $tenantId)
                ->where('session_hash', $sessionHash)
                ->whereDate('created_at', today())
                ->exists();

            if ($existsToday) {
                return; // Skip duplicate
            }

            // ═══════════════════════════════════════════════════════════════
            // RECORD NEW VISIT
            // ═══════════════════════════════════════════════════════════════
            TenantVisit::create([
                'tenant_id' => $tenantId,
                'session_hash' => $sessionHash,
                'user_agent' => mb_substr($userAgent, 0, 512),
                'referer' => mb_substr($request->header('referer') ?? '', 0, 512),
                'device_type' => TenantVisit::detectDeviceType($userAgent),
            ]);

        } catch (\Exception $e) {
            // Silently fail - analytics should never break the main app
            report($e);
        }
    }

    /**
     * Check if user agent indicates a bot.
     */
    protected function isBot(string $userAgent): bool
    {
        return preg_match('/bot|crawl|spider|slurp|googlebot|bingbot|yandex/i', $userAgent) === 1;
    }

    private function resolveTenantFromRoute(mixed $tenant): ?Tenant
    {
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        if (! is_string($tenant) || trim($tenant) === '') {
            return null;
        }

        return Tenant::query()
            ->withoutGlobalScopes()
            ->where('slug', $tenant)
            ->first();
    }
}
