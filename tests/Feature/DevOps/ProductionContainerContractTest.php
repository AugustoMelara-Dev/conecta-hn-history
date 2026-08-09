<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

function block24RuntimeInstructions(string $dockerfile): array
{
    $logical = preg_replace('/\\\\\R[ \t]*/', ' ', $dockerfile);
    $instructions = [];
    $inRuntime = false;

    foreach (preg_split('/\R/', (string) $logical) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (! preg_match('/^([A-Z]+)\s+(.+)$/i', $line, $matches)) {
            continue;
        }

        $instruction = strtoupper($matches[1]);
        $body = trim($matches[2]);

        if ($instruction === 'FROM') {
            if ($inRuntime) {
                break;
            }

            $inRuntime = preg_match('/\s+AS\s+runtime$/i', $body) === 1;

            continue;
        }

        if ($inRuntime) {
            $instructions[] = ['instruction' => $instruction, 'body' => $body];
        }
    }

    if ($instructions === []) {
        throw new RuntimeException('Runtime stage must exist.');
    }

    return $instructions;
}

function assertBlock24RuntimeBuildOrder(string $dockerfile): void
{
    $instructions = block24RuntimeInstructions($dockerfile);
    $composerRuns = [];
    $composerInstallCount = 0;

    foreach ($instructions as $index => $instruction) {
        if ($instruction['instruction'] === 'RUN' && ($count = preg_match_all('/\bcomposer\s+install\b/', $instruction['body'])) > 0) {
            $composerRuns[$index] = $instruction['body'];
            $composerInstallCount += $count;
        }
    }

    if (count($composerRuns) !== 1 || $composerInstallCount !== 1) {
        throw new RuntimeException('Runtime stage must contain exactly one composer install RUN.');
    }

    $composerInstruction = array_key_first($composerRuns);
    $composerRun = $composerRuns[$composerInstruction];
    $composerOffset = strpos($composerRun, 'composer install');
    $composerPosition = ($composerInstruction * 1_000_000) + $composerOffset;

    preg_match('/composer\s+install\s+(.+?)(?=\s+&&|$)/', $composerRun, $composerMatch);
    preg_match_all('/--[A-Za-z0-9-]+/', $composerMatch[1] ?? '', $flagMatches);
    $expectedFlags = [
        '--prefer-dist',
        '--no-interaction',
        '--no-progress',
        '--optimize-autoloader',
        '--classmap-authoritative',
    ];

    if (($flagMatches[0] ?? []) !== $expectedFlags) {
        throw new RuntimeException('Composer install flags must remain canonical and scripts enabled.');
    }

    $mkdirPositions = [];

    foreach ($instructions as $index => $instruction) {
        if ($instruction['instruction'] !== 'RUN') {
            continue;
        }

        preg_match_all('/(?:^|&&)\s*mkdir\s+-p\s+(.+?)(?=\s+&&|$)/', $instruction['body'], $mkdirMatches, PREG_OFFSET_CAPTURE);

        foreach ($mkdirMatches[1] ?? [] as [$arguments, $offset]) {
            foreach (preg_split('/\s+/', trim($arguments)) as $argument) {
                if (! isset($mkdirPositions[$argument])) {
                    $mkdirPositions[$argument] = ($index * 1_000_000) + $offset + strpos($arguments, $argument);
                }
            }
        }
    }

    $laravelDirectories = [
        'bootstrap/cache',
        'storage/app/private',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
    ];

    foreach ($laravelDirectories as $directory) {
        if (! isset($mkdirPositions[$directory])) {
            throw new RuntimeException("Runtime mkdir must include {$directory}.");
        }

        if ($mkdirPositions[$directory] >= $composerPosition) {
            throw new RuntimeException("Runtime mkdir for {$directory} must occur before composer install.");
        }
    }

    $previousPosition = $composerPosition;

    foreach (['php artisan config:clear --quiet', 'php artisan route:clear --quiet', 'php artisan view:clear --quiet'] as $command) {
        $positions = [];

        foreach ($instructions as $index => $instruction) {
            if ($instruction['instruction'] === 'RUN' && ($offset = strpos($instruction['body'], $command)) !== false) {
                $positions[] = ($index * 1_000_000) + $offset;
            }
        }

        if (count($positions) !== 1 || $positions[0] <= $previousPosition) {
            throw new RuntimeException("{$command} must occur exactly once after the previous runtime command.");
        }

        $previousPosition = $positions[0];
    }

    foreach (['/config/caddy', '/data/caddy'] as $directory) {
        if (! isset($mkdirPositions[$directory]) || $mkdirPositions[$directory] <= $previousPosition) {
            throw new RuntimeException("Runtime mkdir for {$directory} must occur after Laravel cache clears.");
        }

        $previousPosition = $mkdirPositions[$directory];
    }

    $chown = 'chown -R www-data:www-data /config/caddy /data/caddy bootstrap/cache storage';
    $chownPositions = [];

    foreach ($instructions as $index => $instruction) {
        if ($instruction['instruction'] === 'RUN' && ($offset = strpos($instruction['body'], $chown)) !== false) {
            $chownPositions[] = [($index * 1_000_000) + $offset, trim(substr($instruction['body'], $offset + strlen($chown)))];
        }
    }

    if (count($chownPositions) !== 1 || $chownPositions[0][0] <= $previousPosition || $chownPositions[0][1] !== '') {
        throw new RuntimeException('Canonical runtime chown must be the final shell command after Caddy directories.');
    }
}

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
        ->not->toContain('--no-scripts')
        ->toContain('HEALTHCHECK')
        ->toContain('USER www-data')
        ->toContain('chown -R www-data:www-data /config/caddy /data/caddy bootstrap/cache storage')
        ->toContain('ENTRYPOINT ["/app/docker/entrypoint.sh"]');

    assertBlock24RuntimeBuildOrder($dockerfile);

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

it('rejects runtime build-order bypasses while allowing a prior runtime mkdir RUN', function (): void {
    $directories = 'bootstrap/cache storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs';
    $composer = 'RUN composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader --classmap-authoritative && php artisan config:clear --quiet && php artisan route:clear --quiet && php artisan view:clear --quiet && mkdir -p /config/caddy /data/caddy && chown -R www-data:www-data /config/caddy /data/caddy bootstrap/cache storage';
    $valid = "FROM node:22 AS frontend\nRUN echo frontend\nFROM php:8.4 AS runtime\nRUN mkdir -p {$directories}\n{$composer}\n";

    expect(fn () => assertBlock24RuntimeBuildOrder($valid))->not->toThrow(RuntimeException::class);

    $adversarial = [
        'composer before directories' => ["FROM php:8.4 AS runtime\n{$composer}\nRUN mkdir -p {$directories}\n", 'must occur before composer install'],
        'missing framework views' => [str_replace(' storage/framework/views', '', $valid), 'must include storage/framework/views'],
        'frontend mkdir only' => ["FROM node:22 AS frontend\nRUN mkdir -p {$directories}\nFROM php:8.4 AS runtime\n{$composer}\n", 'must include bootstrap/cache'],
        'later runtime RUN' => ["FROM php:8.4 AS runtime\n{$composer}\nRUN mkdir -p {$directories}\n", 'must occur before composer install'],
        'duplicate composer RUN' => ["{$valid}RUN composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader --classmap-authoritative\n", 'exactly one composer install RUN'],
        'duplicate composer in one RUN' => [str_replace(' && php artisan config:clear', ' && composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader --classmap-authoritative && php artisan config:clear', $valid), 'exactly one composer install RUN'],
    ];

    foreach ($adversarial as [$fixture, $message]) {
        expect(fn () => assertBlock24RuntimeBuildOrder($fixture))
            ->toThrow(RuntimeException::class, $message);
    }
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
