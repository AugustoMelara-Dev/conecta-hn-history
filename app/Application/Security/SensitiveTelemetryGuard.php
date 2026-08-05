<?php

declare(strict_types=1);

namespace App\Application\Security;

use Laravel\Telescope\IncomingEntry;

/**
 * Keeps Livewire component state out of request telemetry.
 *
 * Livewire serializes form state inside nested component payloads. Telescope's
 * top-level hidden-parameter list cannot reliably redact those structures, so
 * the safe boundary is to omit Livewire request entries entirely.
 */
final class SensitiveTelemetryGuard
{
    public static function shouldExclude(IncomingEntry $entry): bool
    {
        if (! $entry->isRequest()) {
            return false;
        }

        $path = parse_url((string) ($entry->content['uri'] ?? ''), PHP_URL_PATH);

        if (! is_string($path)) {
            return false;
        }

        $normalizedPath = '/'.ltrim($path, '/');

        return $normalizedPath === '/livewire/update'
            || str_starts_with($normalizedPath, '/livewire/message/');
    }
}
