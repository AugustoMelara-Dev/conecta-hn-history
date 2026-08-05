<?php

declare(strict_types=1);

namespace Tests\Support\Channels;

use InvalidArgumentException;
use RuntimeException;

final class Program07CompositeCertificateReport
{
    /** @param array<string, mixed> $facts */
    public static function write(array $facts): void
    {
        $path = (string) getenv('DOVRA_PROGRAM07_COMPOSITE_REPORT');
        if (! self::isAbsolute($path)) {
            throw new InvalidArgumentException('Program 07 report path must be absolute.');
        }
        $parent = dirname($path);
        if (! is_dir($parent) || self::isAlias($parent)) {
            throw new InvalidArgumentException('Program 07 report parent must be a real directory.');
        }
        if (file_exists($path) || is_link($path)) {
            throw new InvalidArgumentException('Program 07 report path must be new and exclusive.');
        }

        $database = (string) getenv('DOVRA_PROGRAM07_COMPOSITE_DATABASE');
        if (! self::isAbsolute($database) || ! is_file($database) || self::isAlias($database)
            || ! self::samePath(dirname($path), dirname($database))) {
            throw new InvalidArgumentException('Program 07 report parent must equal the disposable database parent.');
        }

        self::assertNoLeak($facts);
        $normalized = self::normalize($facts);
        $json = json_encode(
            $normalized,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        )."\n";
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('Program 07 report could not be created exclusively.');
        }
        try {
            if (fwrite($handle, $json) !== strlen($json)) {
                throw new RuntimeException('Program 07 report could not be written completely.');
            }
        } finally {
            fclose($handle);
        }
        if (! is_file($path) || is_link($path) || self::isAlias($path)) {
            throw new RuntimeException('Program 07 report is not a regular non-link file.');
        }
    }

    /** @return array<string, mixed>|list<mixed> */
    private static function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }

    private static function assertNoLeak(mixed $value, string $path = 'facts'): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key)
                    && preg_match('/(?:uuid|tenant_(?:id|uuid|path)|actor_(?:id|uuid)|database|absolute|secret|prompt|message|timestamp|created_at|updated_at|email|payload|token|marker|raw)/iu', $key) === 1
                    && ! in_array($key, [
                        'secret_values_persisted', 'email_active', 'email_capability_allowed', 'signed_email_normalized', 'email_health',
                        'subject_created', 'foreign_created', 'tenant_ids_distinct', 'actor_ids_distinct',
                        'message_count',
                        'conflicting_payload_rejected', 'conflicting_payload_mutations', 'conflicting_payload_exception', 'payload_conflict_rejected',
                        'ai_messages',
                        'outbox_messages',
                        'production_secrets_proven',
                    ], true)) {
                    throw new InvalidArgumentException('Program 07 report contains a runtime or sensitive field at '.$path.'.'.$key.'.');
                }
                self::assertNoLeak($item, $path.'.'.$key);
            }

            return;
        }
        if (! is_string($value)) {
            return;
        }
        if (preg_match('/\b[0-9a-f]{8}-[0-9a-f-]{27,}\b/iu', $value) === 1
            || preg_match('/(?:^|[\\/])(?:[A-Za-z]:)?[\\/]/u', $value) === 1
            || preg_match('/(?:base64:|sk-[A-Za-z0-9_-]{8,}|APP_KEY|BEGIN [A-Z ]+ KEY)/u', $value) === 1
            || preg_match('/(?:system\s+prompt|raw\s+prompt|ciphertext|secret\s+reference|must-not-survive)/iu', $value) === 1
            || preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', $value) === 1) {
            throw new InvalidArgumentException('Program 07 report contains a runtime or sensitive value at '.$path.'.');
        }
    }

    private static function isAlias(string $path): bool
    {
        $cursor = $path;
        while (true) {
            if (is_link($cursor)) {
                return true;
            }
            $real = realpath($cursor);
            if ($real !== false && ! self::samePath($real, $cursor)) {
                return true;
            }
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                return false;
            }
            $cursor = $parent;
        }
    }

    private static function samePath(string $left, string $right): bool
    {
        if (! self::isAbsolute($left) || ! self::isAbsolute($right)) {
            return false;
        }
        $leftReal = realpath($left);
        $rightReal = realpath($right);
        if ($leftReal === false || $rightReal === false) {
            return DIRECTORY_SEPARATOR === '\\'
                ? strcasecmp($left, $right) === 0
                : $left === $right;
        }

        return DIRECTORY_SEPARATOR === '\\'
            ? strcasecmp($leftReal, $rightReal) === 0
            : $leftReal === $rightReal;
    }

    private static function isAbsolute(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1;
    }
}
