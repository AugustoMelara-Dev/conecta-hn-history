<?php

declare(strict_types=1);

/**
 * Vercel serverless filesystem is read-only except /tmp.
 * Force Laravel writable paths to /tmp to avoid runtime 500s.
 */
$tmpStorage = sys_get_temp_dir().'/laravel';
$tmpViews = $tmpStorage.'/framework/views';
$tmpBootstrapCache = sys_get_temp_dir().'/bootstrap/cache';

foreach ([
    $tmpStorage.'/framework/cache',
    $tmpStorage.'/framework/sessions',
    $tmpStorage.'/framework/testing',
    $tmpViews,
    $tmpStorage.'/logs',
    $tmpBootstrapCache,
] as $path) {
    if (! is_dir($path)) {
        @mkdir($path, 0777, true);
    }
}

putenv('APP_STORAGE='.$tmpStorage);
putenv('SESSION_FILES='.$tmpStorage.'/framework/sessions');
putenv('VIEW_COMPILED_PATH='.$tmpViews);
putenv('APP_SERVICES_CACHE='.$tmpBootstrapCache.'/services.php');
putenv('APP_PACKAGES_CACHE='.$tmpBootstrapCache.'/packages.php');
putenv('APP_CONFIG_CACHE='.$tmpBootstrapCache.'/config.php');
putenv('APP_ROUTES_CACHE='.$tmpBootstrapCache.'/routes.php');
putenv('APP_EVENTS_CACHE='.$tmpBootstrapCache.'/events.php');

/**
 * Vercel PHP runtime sets SCRIPT_NAME to /api/index.php for rewrites.
 * Laravel then strips /api from PATH_INFO, breaking /api/* route matching.
 * Normalize server vars so Laravel sees canonical /api paths.
 */
if (($_SERVER['SCRIPT_NAME'] ?? null) === '/api/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    if (isset($_SERVER['PHP_SELF']) && str_starts_with((string) $_SERVER['PHP_SELF'], '/api/index.php')) {
        $_SERVER['PHP_SELF'] = '/index.php'.substr((string) $_SERVER['PHP_SELF'], strlen('/api/index.php'));
    }

    if (isset($_SERVER['PATH_INFO']) && ! str_starts_with((string) $_SERVER['PATH_INFO'], '/api/')) {
        $_SERVER['PATH_INFO'] = '/api'.(string) $_SERVER['PATH_INFO'];
    }
}

if (isset($_GET['__codex_probe']) && in_array((string) getenv('APP_ENV'), ['local', 'testing'], true)) {
    $logFile = $tmpStorage.'/logs/laravel.log';
    $logTail = null;

    if (is_file($logFile)) {
        $logContent = @file_get_contents($logFile);
        if (is_string($logContent)) {
            $logTail = substr($logContent, -4000);
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'cwd' => getcwd(),
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
        'php_self' => $_SERVER['PHP_SELF'] ?? null,
        'path_info' => $_SERVER['PATH_INFO'] ?? null,
        'orig_path_info' => $_SERVER['ORIG_PATH_INFO'] ?? null,
        'has_config_view' => file_exists(__DIR__.'/../config/view.php'),
        'has_bootstrap_providers' => file_exists(__DIR__.'/../bootstrap/providers.php'),
        'has_resources_views' => is_dir(__DIR__.'/../resources/views'),
        'has_public_index' => file_exists(__DIR__.'/../public/index.php'),
        'log_file' => $logFile,
        'log_tail' => $logTail,
    ], JSON_PRETTY_PRINT);
    exit;
}

require __DIR__.'/../public/index.php';
