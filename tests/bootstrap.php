<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/**
 * Read a testing environment value from the available PHP sources.
 */
function testingEnv(string $key, ?string $default = null): ?string
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === '') {
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
 * Use a stable database suffix for single-process runs so consecutive local
 * test invocations can reuse a migrated schema. When another local runner is
 * already active, fall back to a PID-specific database so independent terminal
 * commands cannot drop tables underneath each other.
 */
function testingSingleProcessDatabaseSuffix(): string
{
    $configured = testingEnv('TESTS_MYSQL_SINGLE_PROCESS_SUFFIX');

    if ($configured !== null) {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $configured) ?: 'local';
    }

    if (filter_var(testingEnv('TESTS_MYSQL_SINGLE_PROCESS_USE_PID', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        return testingProcessSuffix();
    }

    if (! testingAcquireStableMysqlDatabaseLock()) {
        testingSetEnv('TESTS_MYSQL_STABLE_DB_LOCKED', 'false');

        return testingProcessSuffix();
    }

    testingSetEnv('TESTS_MYSQL_STABLE_DB_LOCKED', 'true');

    return 'local';
}

function testingAcquireStableMysqlDatabaseLock(): bool
{
    static $lockHandle = null;

    if (is_resource($lockHandle)) {
        return true;
    }

    $lockDirectory = __DIR__.'/../storage/testing';

    if (! is_dir($lockDirectory) && ! @mkdir($lockDirectory, 0777, true) && ! is_dir($lockDirectory)) {
        return false;
    }

    $handle = @fopen($lockDirectory.'/mysql-stable-schema.lock', 'c');

    if (! is_resource($handle)) {
        return false;
    }

    if (! flock($handle, LOCK_EX | LOCK_NB)) {
        @fclose($handle);

        return false;
    }

    $lockHandle = $handle;

    register_shutdown_function(static function () use (&$lockHandle): void {
        if (is_resource($lockHandle)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    });

    return true;
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

    // `php artisan test --parallel` already provisions isolated databases via
    // Laravel ParallelTesting. Re-appending our own process suffix on top of
    // that produces names like `db_test_1_test_1` and causes duplicate
    // migrations/schema drift across workers. We still sanitize the worker
    // database itself because stale MySQL schemas from previous runs can leave
    // a token database partially migrated even when the base testing database
    // is already correct.
    if (filter_var(testingEnv('LARAVEL_PARALLEL_TESTING', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        // In CI, Laravel already provisions clean parallel databases for the
        // worker tokens. Forcing an extra drop/create here can race against
        // that lifecycle and reintroduce schema duplication. Keep the custom
        // reset only for local stale-worker cleanup.
        if (filter_var(testingEnv('CI', 'false'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        resetLaravelParallelMysqlDatabase();

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

/**
 * Isolate non-parallel MySQL runs in a local test database. By default the
 * suffix is stable, which lets repeated local commands reuse a migrated schema.
 */
function bootstrapSingleProcessMysqlDatabase(): void
{
    if (testingRunsInParallel()) {
        return;
    }

    if (! testingSingleProcessMysqlIsolationEnabled()) {
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

    $safeBase = preg_replace('/[^A-Za-z0-9_]/', '_', $baseDatabase) ?: 'testing';
    $safeSuffix = preg_replace('/[^A-Za-z0-9_]/', '_', testingSingleProcessDatabaseSuffix()) ?: 'local';
    $maxBaseLength = max(1, 63 - strlen('_run_') - strlen($safeSuffix));
    $safeBase = substr($safeBase, 0, $maxBaseLength);
    $isolatedDatabase = "{$safeBase}_run_{$safeSuffix}";
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

        $quotedDatabase = str_replace('`', '``', $isolatedDatabase);
        if (testingReusableMysqlSchemaEnabled() && testingDatabaseHasCurrentSchema($pdo, $isolatedDatabase)) {
            testingSetEnv('DB_DATABASE', $isolatedDatabase);
            testingSetEnv('TESTS_MYSQL_SCHEMA_IS_CURRENT', 'true');

            return;
        }

        $pdo->exec("DROP DATABASE IF EXISTS `{$quotedDatabase}`");
        $pdo->exec("CREATE DATABASE `{$quotedDatabase}` CHARACTER SET {$charset} COLLATE {$collation}");
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            "[tests/bootstrap] Unable to prepare the isolated testing database '{$isolatedDatabase}': {$exception->getMessage()}".PHP_EOL
        );
        exit(1);
    }

    testingSetEnv('DB_DATABASE', $isolatedDatabase);
}

function testingReusableMysqlSchemaEnabled(): bool
{
    return filter_var(testingEnv('TESTS_MYSQL_REUSE_SCHEMA', 'true'), FILTER_VALIDATE_BOOLEAN);
}

function testingDatabaseHasCurrentSchema(PDO $pdo, string $database): bool
{
    $quotedDatabase = str_replace("'", "''", $database);

    try {
        $exists = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$quotedDatabase}'")
            ?->fetchColumn();

        if ($exists === false || $exists === null) {
            return false;
        }

        $quotedIdentifier = str_replace('`', '``', $database);
        $marker = $pdo->query("SELECT schema_hash FROM `{$quotedIdentifier}`.`testing_schema_cache` WHERE id = 1")
            ?->fetchColumn();

        if (! is_string($marker) || ! hash_equals(testingMigrationsFingerprint(), $marker)) {
            return false;
        }

        foreach (['migrations', 'users', 'tenants', 'categories', 'cities', 'departments'] as $table) {
            $quotedTable = str_replace("'", "''", $table);
            $tableExists = $pdo->query(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$quotedDatabase}' AND TABLE_NAME = '{$quotedTable}'"
            )?->fetchColumn();

            if ($tableExists === false || $tableExists === null) {
                return false;
            }
        }

        return true;
    } catch (Throwable) {
        return false;
    }
}

function testingSingleProcessMysqlIsolationEnabled(): bool
{
    if (testingRunsInParallel()) {
        return false;
    }

    return filter_var(testingEnv('TESTS_MYSQL_SINGLE_PROCESS_ISOLATION', 'false'), FILTER_VALIDATE_BOOLEAN);
}

/**
 * DatabaseTransactions expects a ready schema before the application boots.
 * The local testing database may be created after any manual migrate:fresh
 * command, so a DatabaseTransactions run needs a schema before Laravel boots.
 */
function bootstrapSingleProcessMysqlSchema(): void
{
    if (! testingSingleProcessMysqlIsolationEnabled()) {
        return;
    }

    $connection = testingEnv('DB_CONNECTION', 'mysql');

    if (! in_array($connection, ['mysql', 'mariadb'], true)) {
        return;
    }

    if (! testingBootstrapMigrationsEnabled()) {
        return;
    }

    if (filter_var(testingEnv('TESTS_MYSQL_SCHEMA_IS_CURRENT', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        return;
    }

    $artisan = realpath(__DIR__.'/../artisan') ?: __DIR__.'/../artisan';
    $process = new Symfony\Component\Process\Process(
        [PHP_BINARY, $artisan, 'migrate', '--env=testing', '--force', '--no-interaction', '--quiet'],
        dirname(__DIR__),
        null,
        null,
        300,
    );

    $process->run();

    if (! $process->isSuccessful()) {
        fwrite(
            STDERR,
            '[tests/bootstrap] Unable to migrate the isolated testing database '
            ."'".(testingEnv('DB_DATABASE') ?? 'unknown')."': "
            .trim($process->getErrorOutput().PHP_EOL.$process->getOutput()).PHP_EOL
        );
        exit(1);
    }

    writeTestingMysqlSchemaMarker();
}

function writeTestingMysqlSchemaMarker(): void
{
    if (! testingReusableMysqlSchemaEnabled()) {
        return;
    }

    $database = testingEnv('DB_DATABASE');

    if ($database === null) {
        return;
    }

    $charset = testingEnv('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';

    try {
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                testingEnv('DB_HOST', '127.0.0.1'),
                testingEnv('DB_PORT', '3306'),
                $database,
                $charset,
            ),
            testingEnv('DB_USERNAME', 'root') ?? 'root',
            testingEnv('DB_PASSWORD', '') ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        );

        $pdo->exec('CREATE TABLE IF NOT EXISTS testing_schema_cache (id TINYINT UNSIGNED PRIMARY KEY, schema_hash CHAR(64) NOT NULL, updated_at TIMESTAMP NULL)');
        $statement = $pdo->prepare('REPLACE INTO testing_schema_cache (id, schema_hash, updated_at) VALUES (1, :schema_hash, CURRENT_TIMESTAMP)');
        $statement->execute(['schema_hash' => testingMigrationsFingerprint()]);
    } catch (Throwable) {
        // Marker writes are an optimization only; a later run will rebuild if
        // the marker is absent or unreadable.
    }
}

function testingMigrationsFingerprint(): string
{
    $files = array_merge(
        glob(__DIR__.'/../database/migrations/*.php') ?: [],
        glob(__DIR__.'/../database/settings/*.php') ?: [],
    );
    sort($files);

    $hash = hash_init('sha256');

    foreach ($files as $file) {
        hash_update($hash, basename($file)."\n");
        hash_update_file($hash, $file);
        hash_update($hash, "\n");
    }

    return hash_final($hash);
}

/**
 * Decide whether bootstrap must migrate before Laravel boots.
 *
 * `RefreshDatabase` performs its own migrate:fresh after the app boots, so
 * doing a bootstrap migration first doubles the cost. `DatabaseTransactions`
 * starts a transaction during the framework test setup and therefore needs a
 * schema already present. The default `auto` mode pays the bootstrap migration
 * cost only when the selected tests actually use DatabaseTransactions.
 */
function testingBootstrapMigrationsEnabled(): bool
{
    $configured = testingEnv('TESTS_MYSQL_BOOTSTRAP_MIGRATIONS', 'auto');

    if ($configured !== null && strtolower($configured) !== 'auto') {
        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }

    return testingSelectedTestsUseDatabaseTransactions();
}

function testingSelectedTestsUseDatabaseTransactions(): bool
{
    $paths = testingSelectedTestPaths();

    if ($paths === []) {
        return true;
    }

    foreach ($paths as $path) {
        if (is_file($path) && testingFileMentionsDatabaseTransactions($path)) {
            return true;
        }

        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if ($item->isFile()
                && $item->getExtension() === 'php'
                && testingFileMentionsDatabaseTransactions($item->getPathname())) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function testingSelectedTestPaths(): array
{
    $paths = [];
    $root = dirname(__DIR__);
    $argv = $_SERVER['argv'] ?? [];

    foreach ($argv as $argument) {
        if (! is_string($argument) || $argument === '' || str_starts_with($argument, '-')) {
            continue;
        }

        $candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $argument);

        if (! str_starts_with($candidate, 'tests'.DIRECTORY_SEPARATOR) && $candidate !== 'tests') {
            continue;
        }

        $absolute = $root.DIRECTORY_SEPARATOR.$candidate;

        if (file_exists($absolute)) {
            $paths[] = $absolute;
        }
    }

    return array_values(array_unique($paths));
}

function testingFileMentionsDatabaseTransactions(string $path): bool
{
    $contents = @file_get_contents($path);

    return is_string($contents) && str_contains($contents, 'DatabaseTransactions');
}

/**
 * Reset Laravel's per-worker MySQL database when running `artisan test --parallel`.
 *
 * Laravel assigns names like `conecta_hn_testing_test_1`. If a previous local
 * run left one of those worker databases half-migrated, subsequent parallel
 * runs can clone or reuse an inconsistent schema even though the base testing
 * database is clean. Recreating only the current worker database keeps the run
 * deterministic without touching the primary testing database.
 */
function resetLaravelParallelMysqlDatabase(): void
{
    $token = testingEnv('TEST_TOKEN');

    if ($token === null) {
        return;
    }

    $connection = testingEnv('DB_CONNECTION', 'mysql');

    if (! in_array($connection, ['mysql', 'mariadb'], true)) {
        return;
    }

    $database = testingEnv('DB_DATABASE');

    if ($database === null) {
        return;
    }

    $safeToken = preg_replace('/[^A-Za-z0-9_]/', '_', $token) ?: '1';
    $expectedSuffix = "_test_{$safeToken}";

    if (! str_ends_with($database, $expectedSuffix)) {
        return;
    }

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

        $quotedDatabase = str_replace('`', '``', $database);
        $pdo->exec("DROP DATABASE IF EXISTS `{$quotedDatabase}`");
        $pdo->exec("CREATE DATABASE `{$quotedDatabase}` CHARACTER SET {$charset} COLLATE {$collation}");
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            "[tests/bootstrap] Unable to reset the Laravel parallel worker database '{$database}': {$exception->getMessage()}".PHP_EOL
        );
        exit(1);
    }
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
bootstrapSingleProcessMysqlDatabase();
ensureTestingMysqlDatabaseAvailable();
bootstrapSingleProcessMysqlSchema();
