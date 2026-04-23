<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class NormalizeInternalRedirectLocation
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! app()->environment('production')) {
            return $response;
        }

        if (! $response->isRedirection()) {
            return $response;
        }

        $location = $response->headers->get('Location');

        if (! is_string($location) || ! Str::startsWith($location, ['http://', 'https://'])) {
            return $response;
        }

        $targetHost = strtolower((string) parse_url($location, PHP_URL_HOST));
        $requestHost = strtolower((string) $request->getHost());

        if ($targetHost === '' || $targetHost === $requestHost) {
            return $response;
        }

        if (! in_array($targetHost, $this->runtimeInternalHosts(), true)) {
            return $response;
        }

        $path = (string) (parse_url($location, PHP_URL_PATH) ?? '/');
        $query = (string) (parse_url($location, PHP_URL_QUERY) ?? '');
        $fragment = (string) (parse_url($location, PHP_URL_FRAGMENT) ?? '');
        $relativeLocation = $path
            .($query !== '' ? '?'.$query : '')
            .($fragment !== '' ? '#'.$fragment : '');

        $response->headers->set('Location', $relativeLocation);

        return $response;
    }

    /**
     * @return array<int, string>
     */
    private function runtimeInternalHosts(): array
    {
        $candidates = [
            (string) config('app.url', ''),
            (string) config('app.platform_app_url', ''),
            (string) config('app.platform_directory_url', ''),
            (string) config('app.platform_admin_url', ''),
            (string) config('surfaces.origins.user_app', ''),
            (string) config('surfaces.origins.business_app', ''),
            (string) config('surfaces.origins.admin_app', ''),
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

            $hosts[] = strtolower($host);
        }

        return array_values(array_unique($hosts));
    }
}
