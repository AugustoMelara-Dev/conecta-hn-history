<?php

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicPath = __DIR__.'/../public';
$requested = realpath($publicPath.$uri);

if ($requested !== false && str_starts_with($requested, realpath($publicPath)) && is_file($requested)) {
    return false;
}

require $publicPath.'/index.php';
