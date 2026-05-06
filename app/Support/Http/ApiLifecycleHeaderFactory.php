<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiLifecycleHeaderFactory
{
    public static function apply(Request $request, Response $response, ?string $version = null): Response
    {
        $version = strtolower(trim((string) ($version ?: self::versionFromRequest($request))));

        if ($version === '') {
            return $response;
        }

        $config = (array) config("api-lifecycle.versions.{$version}", []);
        $supportedVersions = array_values((array) config('api-lifecycle.supported_versions', []));

        $response->headers->set('X-API-Version', $version);
        $response->headers->set('X-API-Supported-Versions', implode(', ', $supportedVersions));

        $policyUrl = trim((string) ($config['policy_url'] ?? ''));
        if ($policyUrl !== '') {
            $response->headers->set('Link', sprintf('<%s>; rel="version-policy"', $policyUrl), false);
        }

        if (($config['status'] ?? 'active') !== 'deprecated') {
            return $response;
        }

        $response->headers->set('Deprecation', 'true');

        $sunsetAt = trim((string) ($config['sunset_at'] ?? ''));
        if ($sunsetAt !== '') {
            $response->headers->set('Sunset', $sunsetAt);
        }

        $successor = trim((string) ($config['successor'] ?? ''));
        if ($successor !== '') {
            $response->headers->set('Link', sprintf('</api/%s>; rel="successor-version"', ltrim($successor, '/')), false);
        }

        return $response;
    }

    private static function versionFromRequest(Request $request): ?string
    {
        $path = trim($request->path(), '/');

        if (preg_match('/^api\/(?<version>v[0-9]+)(\/|$)/', $path, $matches) !== 1) {
            return null;
        }

        return $matches['version'];
    }
}
