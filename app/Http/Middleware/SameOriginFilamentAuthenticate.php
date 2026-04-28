<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

final class SameOriginFilamentAuthenticate extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        $loginUrl = Filament::getLoginUrl();

        if (! is_string($loginUrl) || ! str_starts_with($loginUrl, 'http')) {
            return $loginUrl;
        }

        $targetHost = strtolower((string) parse_url($loginUrl, PHP_URL_HOST));

        if ($targetHost === '' || ! $this->isInternalHost($targetHost)) {
            return $loginUrl;
        }

        $path = (string) (parse_url($loginUrl, PHP_URL_PATH) ?? '/');
        $query = (string) (parse_url($loginUrl, PHP_URL_QUERY) ?? '');
        $fragment = (string) (parse_url($loginUrl, PHP_URL_FRAGMENT) ?? '');

        return $path
            .($query !== '' ? '?'.$query : '')
            .($fragment !== '' ? '#'.$fragment : '');
    }

    private function isInternalHost(string $host): bool
    {
        if (str_ends_with($host, '.up.railway.app') || str_ends_with($host, '.railway.internal')) {
            return true;
        }

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

        foreach ($candidates as $candidate) {
            $candidateHost = parse_url(trim($candidate), PHP_URL_HOST);

            if (is_string($candidateHost) && strtolower($candidateHost) === $host) {
                return true;
            }
        }

        return false;
    }
}
