<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders - Zero-Trust HTTP Security Middleware
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DEFENSE-IN-DEPTH: Browser-Enforced Security Policies                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * THREAT MODEL:
 * - XSS (Cross-Site Scripting): Mitigated by CSP with Nonce
 * - Clickjacking: Eliminated by X-Frame-Options
 * - MIME Sniffing: Blocked by X-Content-Type-Options
 * - SSL Stripping: Prevented by HSTS
 * - Information Disclosure: Headers scrubbed
 *
 * CSP STRATEGY:
 * - Development: Uses 'unsafe-inline' for Vite HMR compatibility
 * - Production: Uses cryptographic nonce for maximum XSS protection
 *
 * APPLIES TO: All HTTP responses (web + API)
 */
class SecurityHeaders
{
    /**
     * Trusted external script sources.
     */
    private const TRUSTED_SCRIPTS = [
        'https://www.googletagmanager.com',
        'https://www.google-analytics.com',
        'https://connect.facebook.net',
        'https://js.stripe.com',
    ];

    /**
     * Trusted external style sources.
     */
    private const TRUSTED_STYLES = [
        'https://fonts.googleapis.com',
        'https://fonts.bunny.net',
    ];

    /**
     * Trusted external connect sources.
     */
    private const TRUSTED_CONNECT = [
        'https://www.google-analytics.com',
        'https://api.stripe.com',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ═══════════════════════════════════════════════════════════════════════
        // NONCE GENERATION: Cryptographic random per-request
        // ═══════════════════════════════════════════════════════════════════════
        $nonce = $this->generateNonce();

        // Store nonce in container for Blade/Inertia access
        app()->instance('csp-nonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        // ═══════════════════════════════════════════════════════════════════════
        // MODULE 1: ENVIRONMENT-AWARE CONTENT SECURITY POLICY
        // ═══════════════════════════════════════════════════════════════════════
        $csp = $this->buildContentSecurityPolicy($nonce);
        $response->headers->set('Content-Security-Policy', $csp);

        // ═══════════════════════════════════════════════════════════════════════
        // MODULE 2: CORE SECURITY HEADERS
        // ═══════════════════════════════════════════════════════════════════════

        // X-Frame-Options: Prevent clickjacking by blocking iframe embedding
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options: Prevent MIME sniffing attacks
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection: Legacy browser XSS filter (defense-in-depth)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer-Policy: Protect user privacy while maintaining analytics
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy: Disable unused browser features
        $response->headers->set(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(self), gyroscope=(), magnetometer=(), microphone=(), payment=(self), usb=()'
        );

        // ═══════════════════════════════════════════════════════════════════════
        // MODULE 3: TRANSPORT SECURITY (HSTS) - Production Only
        // ═══════════════════════════════════════════════════════════════════════
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        if (app()->environment('local')) {
            // Local Filament/Livewire iteration was serving stale pages and assets
            // from browser caches. Make every local response revalidate strictly.
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        // ═══════════════════════════════════════════════════════════════════════
        // MODULE 4: INFORMATION LEAKAGE PREVENTION
        // ═══════════════════════════════════════════════════════════════════════
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        $response->headers->remove('X-Laravel');
        $this->normalizeInternalRedirectLocation($request, $response);

        // Production-only: prevent runtime cross-origin redirect drift in proxied topology.
        if (app()->environment('production')) {
            $this->normalizeInternalRedirectLocation($request, $response);
        }

        return $response;
    }

    /**
     * Generate a cryptographic nonce for CSP.
     */
    private function generateNonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    /**
     * Build Content-Security-Policy based on environment.
     *
     * DEVELOPMENT: Uses 'unsafe-inline' for Vite HMR and Tailwind JIT
     * PRODUCTION: Uses nonce-based policy for maximum XSS protection
     */
    private function buildContentSecurityPolicy(string $nonce): string
    {
        $isProduction = app()->environment('production');
        $runtimeConnectSources = $this->runtimeConnectSources();
        $connectSources = implode(
            ' ',
            array_values(array_unique(array_merge(self::TRUSTED_CONNECT, $runtimeConnectSources)))
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SCRIPT-SRC: Control JavaScript execution
        // ═══════════════════════════════════════════════════════════════════════
        // Production: Nonce-based (blocks all inline scripts without nonce)
        // Development: unsafe-inline (required for Vite HMR)
        $scriptPolicy = $isProduction
            ? "'self' 'nonce-{$nonce}'"
            : "'self' 'unsafe-inline' 'unsafe-eval'";

        // ═══════════════════════════════════════════════════════════════════════
        // VITE DEV SERVER (Local/Staging only)
        // ═══════════════════════════════════════════════════════════════════════
        // NOTE: IPv6 literals like [::1] are rejected by some CSP parsers.
        // We force 'localhost' + '127.0.0.1' which cover most dev setups.
        $viteDevSrc = $isProduction ? '' : ' http://localhost:5173 http://127.0.0.1:5173';
        $viteDevWs = $isProduction ? '' : ' ws://localhost:5173 ws://127.0.0.1:5173';
        $reverbEnabled = config('broadcasting.default') === 'reverb'
            && (string) config('broadcasting.connections.reverb.key', '') !== '';
        $reverbWs = $reverbEnabled ? ' ws://localhost:8080 wss://localhost:8080' : '';

        $scriptSrc = "script-src {$scriptPolicy} ".implode(' ', self::TRUSTED_SCRIPTS).$viteDevSrc;

        // ═══════════════════════════════════════════════════════════════════════
        // STYLE-SRC: Control CSS execution
        // ═══════════════════════════════════════════════════════════════════════
        // Note: Tailwind/React inline styles require 'unsafe-inline' in both envs
        // This is a known limitation - styles are lower risk than scripts
        $styleSrc = "style-src 'self' 'unsafe-inline' ".implode(' ', self::TRUSTED_STYLES).$viteDevSrc;

        // ═══════════════════════════════════════════════════════════════════════
        // BUILD FULL CSP DIRECTIVE
        // ═══════════════════════════════════════════════════════════════════════
        $directives = [
            "default-src 'self'",
            $scriptSrc,
            $styleSrc,
            "img-src 'self' data: https: blob:".$viteDevSrc,
            "manifest-src 'self' https:",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net",
            "connect-src 'self' ".$connectSources.' wss:'.$viteDevSrc.$viteDevWs.$reverbWs.(app()->environment('local') ? ' http://localhost:* http://127.0.0.1:*' : ''),
            "frame-src 'self' https://js.stripe.com https://www.facebook.com",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Collect runtime API origins that may appear in generated routes or redirects.
     *
     * This prevents CSP drift in hybrid Vercel->Railway topologies when absolute URLs
     * are emitted from environment-driven URL generators.
     *
     * @return array<int, string>
     */
    private function runtimeConnectSources(): array
    {
        $candidates = [
            (string) config('app.url', ''),
            (string) config('app.platform_app_url', ''),
            (string) config('app.platform_directory_url', ''),
            (string) config('surfaces.origins.platform_api', ''),
        ];

        $sources = [];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '' || ! Str::startsWith($candidate, ['http://', 'https://'])) {
                continue;
            }

            $parts = parse_url($candidate);
            $scheme = $parts['scheme'] ?? null;
            $host = $parts['host'] ?? null;

            if ($scheme === null || $host === null) {
                continue;
            }

            $port = isset($parts['port']) ? ':'.$parts['port'] : '';
            $sources[] = sprintf('%s://%s%s', $scheme, $host, $port);
        }

        return array_values(array_unique($sources));
    }

    /**
     * Keep redirect locations same-origin when runtime emits an internal absolute URL.
     */
    private function normalizeInternalRedirectLocation(Request $request, Response $response): void
    {
        if (! $response->isRedirection()) {
            return;
        }

        $location = $response->headers->get('Location');

        if (! is_string($location) || ! Str::startsWith($location, ['http://', 'https://'])) {
            return;
        }

        $targetHost = strtolower((string) parse_url($location, PHP_URL_HOST));
        $requestHost = strtolower((string) $request->getHost());

        if ($targetHost === '' || $requestHost === '' || $targetHost === $requestHost) {
            return;
        }

        if (! in_array($targetHost, $this->runtimeInternalHosts(), true) && ! $this->isRuntimeInternalHost($targetHost)) {
            return;
        }

        $path = (string) (parse_url($location, PHP_URL_PATH) ?? '/');
        $query = (string) (parse_url($location, PHP_URL_QUERY) ?? '');
        $fragment = (string) (parse_url($location, PHP_URL_FRAGMENT) ?? '');
        $relativeLocation = $path
            .($query !== '' ? '?'.$query : '')
            .($fragment !== '' ? '#'.$fragment : '');

        $response->headers->set('Location', $relativeLocation);
    }

    /**
     * @return array<int, string>
     */
    private function runtimeInternalHosts(): array
    {
        $candidates = [
            (string) config('app.url', ''),
            (string) config('surfaces.origins.platform_api', ''),
        ];

        $hosts = [];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '' || ! Str::startsWith($candidate, ['http://', 'https://'])) {
                continue;
            }

            $host = parse_url($candidate, PHP_URL_HOST);

            if (! is_string($host) || $host === '') {
                continue;
            }

            $host = strtolower($host);

            if (! $this->isRuntimeInternalHost($host)) {
                continue;
            }

            $hosts[] = $host;
        }

        return array_values(array_unique($hosts));
    }

    private function isRuntimeInternalHost(string $host): bool
    {
        return str_ends_with($host, '.up.railway.app')
            || str_ends_with($host, '.railway.internal');
    }
}
