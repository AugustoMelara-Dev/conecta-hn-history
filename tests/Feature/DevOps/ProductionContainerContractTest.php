<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

it('builds one production image for web workers and scheduler', function (): void {
    $root = dirname(__DIR__, 3);
    $dockerfile = file_get_contents($root.'/Dockerfile');
    $entrypoint = file_get_contents($root.'/docker/entrypoint.sh');
    $caddy = file_get_contents($root.'/docker/Caddyfile');

    expect($dockerfile)
        ->toContain('FROM node:22.13-bookworm-slim AS frontend')
        ->toContain('FROM dunglas/frankenphp:1-php8.4-bookworm AS runtime')
        ->toContain('composer install')
        ->toContain('php artisan config:clear --quiet')
        ->toContain('--no-dev')
        ->toContain('HEALTHCHECK')
        ->toContain('USER www-data')
        ->toContain('chown -R www-data:www-data /config/caddy /data/caddy bootstrap/cache storage')
        ->toContain('ENTRYPOINT ["/app/docker/entrypoint.sh"]');

    self::assertLessThan(
        strpos($entrypoint, 'config:production-guard'),
        strpos($entrypoint, 'config:clear'),
        'Runtime configuration must be cleared before production guards read ECS environment variables.',
    );

    expect($entrypoint)
        ->toContain('CONTAINER_ROLE')
        ->toContain('exec frankenphp run')
        ->toContain('exec php artisan horizon')
        ->toContain('exec php artisan schedule:work')
        ->not->toContain('php artisan serve');

    expect($caddy)
        ->toContain('auto_https off')
        ->toContain('php_server');
});

it('does not load development-only telescope in the production dependency graph', function (): void {
    $providers = file_get_contents(dirname(__DIR__, 3).'/bootstrap/providers.php');

    expect($providers)
        ->toContain('use Laravel\\Telescope\\TelescopeApplicationServiceProvider;')
        ->toContain('use App\\Providers\\TelescopeServiceProvider;')
        ->toContain('class_exists(TelescopeApplicationServiceProvider::class)')
        ->toContain('$providers[] = TelescopeServiceProvider::class;')
        ->not->toContain("    App\\Providers\\TelescopeServiceProvider::class,\n");
});

it('makes the production image a fail closed launch certification dependency', function (): void {
    $workflowPath = dirname(__DIR__, 3).'/.github/workflows/ci.yml';
    $workflow = file_get_contents($workflowPath);
    $parsed = Yaml::parseFile($workflowPath);

    expect($parsed)->toBeArray()
        ->and($parsed['jobs']['production-image'] ?? null)->toBeArray()
        ->and($parsed['jobs']['launch-certification']['needs'] ?? [])
        ->toContain('production-image')
        ->and($workflow)
        ->toContain('PRODUCTION_IMAGE: ${{ needs.production-image.result }}')
        ->toContain('"$PRODUCTION_IMAGE"');
});
