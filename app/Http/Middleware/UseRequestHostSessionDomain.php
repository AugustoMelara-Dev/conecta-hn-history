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

        if (is_string($configuredDomain) && $configuredDomain !== '') {
            $host = strtolower($request->getHost());
            $domain = ltrim(strtolower($configuredDomain), '.');
            $hostMatchesDomain = $host === $domain || str_ends_with($host, '.'.$domain);
            $hostIsLocal = $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false;

            if ($hostIsLocal || ! $hostMatchesDomain) {
                config(['session.domain' => null]);
            }
        }

        return $next($request);
    }
}
