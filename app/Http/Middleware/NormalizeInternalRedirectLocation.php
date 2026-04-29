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

        $this->normalizeRedirectLocation($request, $response);
        $this->normalizeInertiaLocation($request, $response);

        return $response;
    }

    private function normalizeRedirectLocation(Request $request, Response $response): void
    {
        if (! $response->isRedirection()) {
            return;
        }

        $location = $response->headers->get('Location');
        $normalized = $this->internalLocationPath($request, $location);

        if ($normalized === null) {
            return;
        }

        $response->headers->set('Location', $normalized);
    }

    private function normalizeInertiaLocation(Request $request, Response $response): void
    {
        $location = $response->headers->get('X-Inertia-Location');
        $normalized = $this->internalLocationPath($request, $location);

        if ($normalized === null) {
            return;
        }

        $response->headers->set('X-Inertia-Location', $this->requestOrigin($request).$normalized);
    }

    private function internalLocationPath(Request $request, mixed $location): ?string
    {
        if (! is_string($location) || ! Str::startsWith($location, ['http://', 'https://'])) {
            return null;
        }

        $targetHost = $this->normalizeHost((string) parse_url($location, PHP_URL_HOST));
        $requestHost = $this->requestHost($request);

        if ($targetHost === '') {
            return null;
        }

        $isRuntimeInternal = in_array($targetHost, $this->runtimeInternalHosts(), true);
        $isRailwayHost = $this->isRailwayHost($targetHost);

        if (! $isRuntimeInternal && ! $isRailwayHost) {
            return null;
        }

        if (! $isRailwayHost && $targetHost !== $requestHost) {
            return null;
        }

        $path = (string) (parse_url($location, PHP_URL_PATH) ?? '');
        $query = (string) (parse_url($location, PHP_URL_QUERY) ?? '');
        $fragment = (string) (parse_url($location, PHP_URL_FRAGMENT) ?? '');

        if ($path === '') {
            $path = $request->getPathInfo() ?: '/';
        }

        return $path
            .($query !== '' ? '?'.$query : '')
            .($fragment !== '' ? '#'.$fragment : '');
    }

    private function requestOrigin(Request $request): string
    {
        $proto = trim((string) $request->headers->get('x-forwarded-proto', ''));
        $proto = $proto !== '' ? strtolower(trim(explode(',', $proto, 2)[0])) : $request->getScheme();
        $host = $this->requestHost($request);

        if ($this->isRailwayHost($host)) {
            $publicOrigin = $this->publicOrigin();

            if ($publicOrigin !== null) {
                return $publicOrigin;
            }
        }

        return sprintf(
            '%s://%s',
            $proto !== '' ? $proto : 'https',
            $host
        );
    }

    private function publicOrigin(): ?string
    {
        $candidates = [
            (string) config('app.platform_directory_url', ''),
            (string) config('app.platform_app_url', ''),
            (string) config('app.url', ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = rtrim(trim($candidate), '/');

            if ($candidate === '' || ! Str::startsWith($candidate, ['http://', 'https://'])) {
                continue;
            }

            $host = $this->normalizeHost((string) parse_url($candidate, PHP_URL_HOST));

            if ($host === '' || $this->isRailwayHost($host)) {
                continue;
            }

            return $candidate;
        }

        return null;
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

    private function requestHost(Request $request): string
    {
        $forwardedHost = $this->normalizeHost((string) $request->headers->get('x-forwarded-host', ''));

        if ($forwardedHost !== '') {
            return $forwardedHost;
        }

        $originalHost = $this->normalizeHost((string) $request->headers->get('x-original-host', ''));

        if ($originalHost !== '') {
            return $originalHost;
        }

        return $this->normalizeHost((string) $request->getHost());
    }

    private function normalizeHost(string $host): string
    {
        $host = trim($host);

        if ($host === '') {
            return '';
        }

        if (str_contains($host, ',')) {
            $host = trim(explode(',', $host, 2)[0]);
        }

        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = preg_replace('#[:/].*$#', '', $host) ?? $host;

        return strtolower(trim($host));
    }

    private function isRailwayHost(string $host): bool
    {
        return str_ends_with($host, '.up.railway.app')
            || str_ends_with($host, '.railway.internal');
    }
}
