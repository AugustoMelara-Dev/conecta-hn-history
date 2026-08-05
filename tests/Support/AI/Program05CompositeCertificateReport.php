<?php

declare(strict_types=1);

namespace Tests\Support\AI;

use InvalidArgumentException;
use RuntimeException;

final class Program05CompositeCertificateReport
{
    /** @param array<string, mixed> $facts */
    public static function writeExclusive(string $path, array $facts): void
    {
        if (! self::isAbsolute($path)) {
            throw new InvalidArgumentException('Program 05 report path must be absolute.');
        }
        $parent = dirname($path);
        if (! is_dir($parent)) {
            throw new InvalidArgumentException('Program 05 report parent directory does not exist.');
        }

        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('Program 05 report path must be new and exclusive.');
        }

        try {
            $encoded = json_encode(
                $facts,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            )."\n";
            if (fwrite($handle, $encoded) !== strlen($encoded)) {
                throw new RuntimeException('Program 05 report write was incomplete.');
            }
        } finally {
            fclose($handle);
        }
    }

    private static function isAbsolute(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1;
    }
}
