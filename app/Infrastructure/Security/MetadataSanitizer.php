<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

/**
 * MetadataSanitizer - Recursive JSON Payload Neutralizer
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  XSS PREVENTION: Strips HTML tags from all string values.             ║
 * ║  DoS PREVENTION: Prunes arrays deeper than MAX_DEPTH levels.          ║
 * ║  DB OPTIMIZATION: Truncates strings beyond MAX_STRING_LENGTH.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class MetadataSanitizer
{
    private const MAX_DEPTH = 3;
    private const MAX_STRING_LENGTH = 500;

    /**
     * Recursively sanitize a metadata array.
     *
     * @param  array<mixed>  $data   Raw metadata payload
     * @param  int           $depth  Current recursion depth (internal)
     * @return array<mixed>          Sanitized payload
     */
    public static function clean(array $data, int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return []; // Prune beyond max depth
        }

        $sanitized = [];

        foreach ($data as $key => $value) {
            // Sanitize key
            $safeKey = strip_tags((string) $key);

            if (is_array($value)) {
                $sanitized[$safeKey] = self::clean($value, $depth + 1);
            } elseif (is_string($value)) {
                $clean = strip_tags($value);
                $sanitized[$safeKey] = mb_substr($clean, 0, self::MAX_STRING_LENGTH);
            } elseif (is_scalar($value) || is_null($value)) {
                $sanitized[$safeKey] = $value;
            }
            // Non-scalar, non-array values (objects, resources) are silently dropped
        }

        return $sanitized;
    }
}
