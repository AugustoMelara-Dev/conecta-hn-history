<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        // ═══════════════════════════════════════════════════════════════════════
        // MODULE 4: INFORMATION LEAKAGE PREVENTION
        // ═══════════════════════════════════════════════════════════════════════
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        $response->headers->remove('X-Laravel');

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
        
        // ═══════════════════════════════════════════════════════════════════════
        // SCRIPT-SRC: Control JavaScript execution
        // ═══════════════════════════════════════════════════════════════════════
        // Production: Nonce-based (blocks all inline scripts without nonce)
        // Development: unsafe-inline (required for Vite HMR)
        $scriptPolicy = $isProduction
            ? "'self' 'nonce-{$nonce}'" 
            : "'self' 'unsafe-inline'";
        
        $scriptSrc = "script-src {$scriptPolicy} " . implode(' ', self::TRUSTED_SCRIPTS);

        // ═══════════════════════════════════════════════════════════════════════
        // STYLE-SRC: Control CSS execution
        // ═══════════════════════════════════════════════════════════════════════
        // Note: Tailwind/React inline styles require 'unsafe-inline' in both envs
        // This is a known limitation - styles are lower risk than scripts
        $styleSrc = "style-src 'self' 'unsafe-inline' " . implode(' ', self::TRUSTED_STYLES);

        // ═══════════════════════════════════════════════════════════════════════
        // BUILD FULL CSP DIRECTIVE
        // ═══════════════════════════════════════════════════════════════════════
        $directives = [
            "default-src 'self'",
            $scriptSrc,
            $styleSrc,
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net",
            "connect-src 'self' " . implode(' ', self::TRUSTED_CONNECT) . " wss:",
            "frame-src 'self' https://js.stripe.com https://www.facebook.com",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }
}
