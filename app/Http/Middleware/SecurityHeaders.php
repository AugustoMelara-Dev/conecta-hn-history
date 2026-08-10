<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders - Middleware que agrega security headers.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '0');

        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        if ((bool) config('security.hsts.enabled', true) && $request->isSecure()) {
            $hsts = 'max-age='.max(0, (int) config('security.hsts.max_age', 31536000));

            if ((bool) config('security.hsts.include_subdomains', false)) {
                $hsts .= '; includeSubDomains';
            }

            if ((bool) config('security.hsts.preload', false)) {
                $hsts .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $hsts);
        } else {
            $response->headers->remove('Strict-Transport-Security');
        }

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            'trusted-types dovra-policy',
        ]).';');

        return $response;
    }
}
