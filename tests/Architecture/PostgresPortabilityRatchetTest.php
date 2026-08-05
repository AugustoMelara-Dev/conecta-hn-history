<?php

declare(strict_types=1);

function sqlStringLiterals(string $path): string
{
    $literals = [];

    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            $literals[] = $token[1];
        }
    }

    return implode("\n", $literals);
}

/** @return list<string> */
function portabilityPhpFiles(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getRealPath();
        }
    }

    return $files;
}

it('forbids unscoped mysql sql in active application strings', function (): void {
    $root = dirname(__DIR__, 2);
    $app = $root.DIRECTORY_SEPARATOR.'app';
    $approved = [
        realpath($app.DIRECTORY_SEPARATOR.'Infrastructure/Persistence/QueryStrategist.php'),
    ];
    $patterns = [
        '/\bFIELD\s*\(/i',
        '/\bON\s+DUPLICATE\s+KEY\b/i',
        '/\bINSERT\s+IGNORE\b/i',
        '/\b(?:FORCE|USE|IGNORE)\s+INDEX\b/i',
        '/\bALTER\s+TABLE\b[^\n]*\bMODIFY\s+(?:COLUMN\s+)?/i',
    ];
    $violations = [];

    foreach (portabilityPhpFiles($app) as $path) {
        $sql = sqlStringLiterals($path);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql) === 1 && ! in_array($path, $approved, true)) {
                $violations[] = str_replace($root.DIRECTORY_SEPARATOR, '', $path)." matches {$pattern}";
            }
        }
    }

    expect($violations)->toBe([]);
});

it('keeps mysql index hints behind an explicit mysql driver gate', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Infrastructure/Persistence/QueryStrategist.php');

    expect($source)
        ->toContain("getDriverName() !== 'mysql'")
        ->toContain("return '';");
});

it('forbids ulids at uuid outbox persistence boundaries', function (): void {
    $root = dirname(__DIR__, 2);
    $violations = [];

    foreach (portabilityPhpFiles($root.DIRECTORY_SEPARATOR.'app') as $path) {
        $source = (string) file_get_contents($path);

        if (str_contains($source, 'OutboxMessage') && preg_match('/[\'\"]id[\'\"]\s*=>[^\n]*Str::ulid\s*\(/', $source) === 1) {
            $violations[] = str_replace($root.DIRECTORY_SEPARATOR, '', $path);
        }
    }

    expect($violations)->toBe([]);
});

it('allowlists mysql-only historical migration sql precisely', function (): void {
    $root = dirname(__DIR__, 2);
    $approved = [
        '2025_12_23_013746_add_performance_indexes.php',
        '2025_12_29_010000_add_free_shipping_type_to_coupons.php',
        '2026_05_11_024000_align_activity_log_and_color_schema_for_uuid_testing.php',
    ];
    $violations = [];

    foreach (portabilityPhpFiles($root.DIRECTORY_SEPARATOR.'database/migrations') as $path) {
        $sql = sqlStringLiterals($path);
        $filename = basename($path);

        if (preg_match('/\b(?:FIELD\s*\(|ON\s+DUPLICATE\s+KEY|INSERT\s+IGNORE|ALGORITHM\s*=)|\bALTER\s+TABLE\b[^\n]*\bMODIFY\s+(?:COLUMN\s+)?/i', $sql) === 1
            && ! in_array($filename, $approved, true)) {
            $violations[] = $filename;
        }
    }

    expect($violations)->toBe([]);
});

it('requires each historical sql exception to retain its driver boundary', function (): void {
    $migrations = dirname(__DIR__, 2).'/database/migrations/';
    $requiredMarkers = [
        '2025_12_23_013746_add_performance_indexes.php' => [
            'getDriverName()',
            "\$driver === 'mysql' || \$driver === 'mariadb'",
        ],
        '2025_12_29_010000_add_free_shipping_type_to_coupons.php' => [
            "\$driver === 'sqlite'",
            "\$driver === 'mysql'",
            "\$driver === 'pgsql'",
            'Unsupported database driver',
        ],
        '2026_05_11_024000_align_activity_log_and_color_schema_for_uuid_testing.php' => [
            "getDriverName() !== 'mysql'",
        ],
    ];

    foreach ($requiredMarkers as $filename => $markers) {
        $source = (string) file_get_contents($migrations.$filename);

        foreach ($markers as $marker) {
            expect($source)->toContain($marker);
        }
    }
});
