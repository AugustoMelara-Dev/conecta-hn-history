<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class UseRequestHostSessionDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredDomain = config('session.domain');

        if (! is_string($configuredDomain) || $configuredDomain === '') {
            return $next($request);
        }

        $normalizedConfigured = ltrim(strtolower($configuredDomain), '.');
        $requestHost = strtolower((string) $request->getHost());

        if ($requestHost === '' || $normalizedConfigured === '') {
            return $next($request);
        }

        $isSameHost = $requestHost === $normalizedConfigured;
        $isSubdomain = str_ends_with($requestHost, '.'.$normalizedConfigured);

        if (! $isSameHost && ! $isSubdomain) {
            config([
                'session.domain' => null,
            ]);
        }

        return $next($request);
    }
}
