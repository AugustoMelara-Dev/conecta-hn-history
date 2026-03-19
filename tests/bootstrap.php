<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

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
