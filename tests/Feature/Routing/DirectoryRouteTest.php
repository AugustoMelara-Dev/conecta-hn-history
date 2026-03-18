<?php

declare(strict_types=1);

/**
 * Directory Route Resolution Tests
 *
 * INVARIANT: route('directory.index') must resolve to /directorio,
 * served by the Hexagonal DirectoryController — never the legacy
 * /directory inline closure.
 */
it('resolves the directory index route to the hexagonal controller path', function () {
    $resolvedUrl = route('directory.index', [], false); // relative path

    expect($resolvedUrl)->toBe('/directorio');
});

it('does not register a /directory route', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes());

    $legacyRoute = $routes->first(fn ($r) => $r->uri() === 'directory');

    expect($legacyRoute)->toBeNull('Legacy /directory route must not exist');
});
