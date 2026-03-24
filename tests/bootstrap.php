<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/**
 * Read a testing environment value from the available PHP sources.
 */
function testingEnv(string $key, ?string $default = null): ?string
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

/**
 * Synchronize an environment value across getenv, $_ENV and $_SERVER.
 */
function testingSetEnv(string $key, string $value): void
{
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

/**
 * When Pest runs in parallel directly, Laravel's native database cloning flow
 * is skipped. We provision a per-process database up front so RefreshDatabase
 * does not make multiple workers fight over the same schema.
 */
function bootstrapParallelMysqlDatabase(): void
{
    $token = testingEnv('TEST_TOKEN');
    $isNativeLaravelParallel = filter_var(
        testingEnv('LARAVEL_PARALLEL_TESTING', 'false'),
        FILTER_VALIDATE_BOOLEAN
    );

    if ($token === null || $isNativeLaravelParallel) {
        return;
    }

    $connection = testingEnv('DB_CONNECTION', 'mysql');

    if (! in_array($connection, ['mysql', 'mariadb'], true)) {
        return;
    }

    $baseDatabase = testingEnv('DB_DATABASE');

    if ($baseDatabase === null) {
        return;
    }

    $safeToken = preg_replace('/[^A-Za-z0-9_]/', '_', $token) ?: '1';
    $parallelDatabase = "{$baseDatabase}_test_{$safeToken}";
    $charset = preg_replace('/[^A-Za-z0-9_]/', '', testingEnv('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4') ?: 'utf8mb4';
    $collation = preg_replace('/[^A-Za-z0-9_]/', '', testingEnv('DB_COLLATION', 'utf8mb4_unicode_ci') ?? 'utf8mb4_unicode_ci') ?: 'utf8mb4_unicode_ci';

    try {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                testingEnv('DB_HOST', '127.0.0.1'),
                testingEnv('DB_PORT', '3306'),
                $charset,
            ),
            testingEnv('DB_USERNAME', 'root') ?? 'root',
            testingEnv('DB_PASSWORD', '') ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        );

        $quotedDatabase = str_replace('`', '``', $parallelDatabase);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$quotedDatabase}` CHARACTER SET {$charset} COLLATE {$collation}");
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            "[tests/bootstrap] Unable to prepare the parallel testing database '{$parallelDatabase}': {$exception->getMessage()}".PHP_EOL
        );
        exit(1);
    }

    testingSetEnv('DB_DATABASE', $parallelDatabase);
}

$bootstrapCacheFiles = [
    __DIR__.'/../bootstrap/cache/config.php',
    __DIR__.'/../bootstrap/cache/events.php',
    __DIR__.'/../bootstrap/cache/settings.php',
    __DIR__.'/../bootstrap/cache/blade-icons.php',
];

foreach ($bootstrapCacheFiles as $cacheFile) {
    if (is_file($cacheFile)) {
        @unlink($cacheFile);
    }
}

foreach (glob(__DIR__.'/../bootstrap/cache/routes-*.php') ?: [] as $routeCacheFile) {
    @unlink($routeCacheFile);
}

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

bootstrapParallelMysqlDatabase();
