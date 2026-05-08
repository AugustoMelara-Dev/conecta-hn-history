<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NormalizeInternalRedirectLocation
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->isRedirection()) {
            return $response;
        }

        $location = $response->headers->get('Location');
        $targetHost = is_string($location) ? strtolower((string) parse_url($location, PHP_URL_HOST)) : '';

        if (
            is_string($location)
            && str_starts_with($location, 'http')
            && $targetHost !== ''
            && (str_ends_with($targetHost, '.up.railway.app') || str_ends_with($targetHost, '.railway.internal'))
        ) {
            $path = (string) (parse_url($location, PHP_URL_PATH) ?? '/');
            $query = (string) (parse_url($location, PHP_URL_QUERY) ?? '');
            $fragment = (string) (parse_url($location, PHP_URL_FRAGMENT) ?? '');

            $response->headers->set(
                'Location',
                $path
                .($query !== '' ? '?'.$query : '')
                .($fragment !== '' ? '#'.$fragment : '')
            );
        }

        return $response;
    }
}
