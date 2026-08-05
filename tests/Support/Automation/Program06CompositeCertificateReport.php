<?php

declare(strict_types=1);

namespace Tests\Support\Automation;

use InvalidArgumentException;
use RuntimeException;

final class Program06CompositeCertificateReport
{
    /**
     * @param array<string, mixed> $facts
     */
    public static function writeExclusive(string $path, array $facts): void
    {
        if (! self::isAbsolute($path)) {
            throw new InvalidArgumentException('Program 06 report path must be absolute.');
        }

        $parent = dirname($path);
        if (! is_dir($parent)) {
            throw new InvalidArgumentException('Program 06 report parent directory does not exist.');
        }
        if (file_exists($path)) {
            throw new InvalidArgumentException('Program 06 report path must be new and exclusive.');
        }

        self::assertNoLeak($facts);

        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('Program 06 report path must be new and exclusive.');
        }

        try {
            $encoded = json_encode(
                $facts,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            )."\n";
            $written = fwrite($handle, $encoded);
            if ($written !== strlen($encoded)) {
                throw new RuntimeException('Program 06 report write was incomplete.');
            }
        } finally {
            fclose($handle);
        }
    }

    private static function assertNoLeak(mixed $value, string $path = 'facts'): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $keyString = (string) $key;
                if (preg_match('/(?:uuid|tenant_(?:id|uuid|path)|(?:^|_)actor(?:_id|_uuid)$|database|absolute|secret|prompt|message|timestamp|created_at|updated_at|email|payload|token|marker|raw)/iu', $keyString) === 1) {
                    throw new InvalidArgumentException("Program 06 report contains a runtime or sensitive field: {$path}.{$keyString}");
                }
                self::assertNoLeak($item, $path.'.'.$keyString);
            }

            return;
        }

        if (! is_string($value)) {
            return;
        }

        if (preg_match('/\b[0-9a-f]{8}-[0-9a-f-]{27,}\b/iu', $value) === 1
            || preg_match('/(?:^|[\\\\\/])(?:[A-Za-z]:)?[\\\\\/]/u', $value) === 1
            || preg_match('/(?:base64:|sk-[A-Za-z0-9_-]{8,}|APP_KEY|BEGIN [A-Z ]+ KEY)/u', $value) === 1
            || preg_match('/(?:system\s+prompt|raw\s+prompt|ciphertext|secret\s+reference|must-not-survive)/iu', $value) === 1
            || preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', $value) === 1) {
            throw new InvalidArgumentException("Program 06 report contains a runtime or sensitive value at {$path}.");
        }
    }

    private static function isAbsolute(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1;
    }
}
