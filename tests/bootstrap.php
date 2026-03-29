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
 * Get a stable process suffix for filesystem isolation during tests.
 */
function testingProcessSuffix(): string
{
    $token = testingEnv('TEST_TOKEN');

    if ($token !== null) {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $token) ?: '1';
    }

    $pid = function_exists('getmypid') ? getmypid() : null;

    if (is_int($pid) && $pid > 0) {
        return "pid_{$pid}";
    }

    return 'main';
}

/**
 * Recursively delete a directory tree.
 */
function testingDeleteDirectory(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($path);
}

/**
 * Isolate runtime filesystem paths per test process so Blade, cache and
 * session artifacts do not contend on Windows or during concurrent Pest runs.
 */
function configureTestingRuntimeStorage(): void
{
    $suffix = testingProcessSuffix();
    $runtimeStoragePath = __DIR__."/../storage/testing/{$suffix}";
    $frameworkPath = "{$runtimeStoragePath}/framework";
    $compiledViewPath = "{$frameworkPath}/views";

    $directories = [
        $runtimeStoragePath,
        $frameworkPath,
        "{$frameworkPath}/cache",
        "{$frameworkPath}/cache/data",
        "{$frameworkPath}/sessions",
        "{$frameworkPath}/testing",
        $compiledViewPath,
        "{$runtimeStoragePath}/app",
        "{$runtimeStoragePath}/logs",
    ];

    foreach ($directories as $directory) {
        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            fwrite(STDERR, "[tests/bootstrap] Unable to create runtime testing directory '{$directory}'.".PHP_EOL);
            exit(1);
        }
    }

    testingSetEnv('LARAVEL_STORAGE_PATH', realpath($runtimeStoragePath) ?: $runtimeStoragePath);
    testingSetEnv('VIEW_COMPILED_PATH', realpath($compiledViewPath) ?: $compiledViewPath);

    register_shutdown_function(static function () use ($runtimeStoragePath): void {
        testingDeleteDirectory($runtimeStoragePath);
    });
}

/**
 * When Pest runs in parallel directly, Laravel's native database cloning flow
 * is skipped. We provision a per-process database up front so RefreshDatabase
 * does not make multiple workers fight over the same schema.
 */
function bootstrapParallelMysqlDatabase(): void
{
    if (! testingRunsInParallel()) {
        return;
    }

    $token = testingEnv('TEST_TOKEN');

    if ($token === null) {
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

function testingRunsInParallel(): bool
{
    if (filter_var(testingEnv('LARAVEL_PARALLEL_TESTING', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        return true;
    }

    $argv = $_SERVER['argv'] ?? [];

    foreach ($argv as $argument) {
        if ($argument === '--parallel' || $argument === '-p') {
            return true;
        }
    }

    return false;
}

function ensureTestingMysqlDatabaseAvailable(): void
{
    $connection = testingEnv('DB_CONNECTION', 'mysql');

    if (! in_array($connection, ['mysql', 'mariadb'], true)) {
        fwrite(
            STDERR,
            "[tests/bootstrap] Unsupported testing database connection '{$connection}'. The testing suite is pinned to MySQL.".PHP_EOL
        );
        exit(1);
    }

    $host = testingEnv('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
    $port = testingEnv('DB_PORT', '3306') ?? '3306';
    $username = testingEnv('DB_USERNAME', 'root') ?? 'root';
    $password = testingEnv('DB_PASSWORD', '') ?? '';
    $charset = testingEnv('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';

    try {
        new PDO(
            sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset),
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        return;
    } catch (Throwable) {
        fwrite(
            STDERR,
            "[tests/bootstrap] Unable to connect to MySQL at {$host}:{$port} for testing. Start the local MySQL service and ensure credentials in .env.testing/phpunit.xml are valid.".PHP_EOL
        );
        exit(1);
    }
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

configureTestingRuntimeStorage();
bootstrapParallelMysqlDatabase();
ensureTestingMysqlDatabaseAvailable();
