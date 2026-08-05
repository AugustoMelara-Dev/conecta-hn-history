<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

function runtimeComposerManifest(): array
{
    $decoded = json_decode(
        File::get(base_path('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($decoded)->toBeArray();

    return $decoded;
}

it('targets the supported Laravel 13 runtime and test stack', function (): void {
    $composer = runtimeComposerManifest();

    expect($composer['require']['php'] ?? null)->toBe('^8.4')
        ->and($composer['config']['platform']['php'] ?? null)->toMatch('/^8\.4\.\d+$/')
        ->and($composer['require']['laravel/framework'] ?? null)->toBe('^13.0')
        ->and($composer['require']['laravel/tinker'] ?? null)->toBe('^3.0')
        ->and($composer['require-dev']['pestphp/pest'] ?? null)->toBe('^4.0')
        ->and($composer['require-dev']['pestphp/pest-plugin-arch'] ?? null)->toBe('^4.0')
        ->and($composer['require-dev']['pestphp/pest-plugin-laravel'] ?? null)->toBe('^4.0')
        ->and($composer['require-dev']['phpunit/phpunit'] ?? null)->toBe('^12.0');
});

it('uses PHP 8.4 in every active CI and production runtime', function (): void {
    $workflow = File::get(base_path('.github/workflows/ci.yml'));
    $dockerfile = File::get(base_path('Dockerfile'));

    expect($workflow)->not->toMatch('/Setup PHP 8\.[23]|php-version:\s*["\']8\.[23]["\']/')
        ->and($workflow)->toContain('php-version: "8.4"')
        ->and($dockerfile)->toContain('frankenphp:1-php8.4-bookworm')
        ->and($dockerfile)->not->toContain('php8.2');
});

it('forces the testing environment when tests run inside a production image', function (): void {
    foreach (['phpunit.xml', 'phpunit.sqlite.xml'] as $configuration) {
        $xml = File::get(base_path($configuration));

        expect($xml)
            ->toContain('<env name="APP_ENV" value="testing" force="true"/>')
            ->toContain('<server name="APP_ENV" value="testing" force="true"/>');
    }
});

it('does not reference deprecated request forgery middleware directly', function (): void {
    $roots = ['app', 'bootstrap', 'routes', 'tests'];
    $violations = [];

    foreach ($roots as $root) {
        foreach (File::allFiles(base_path($root)) as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === basename(__FILE__)) {
                continue;
            }

            $contents = File::get($file->getPathname());
            if (preg_match('/\b(?:VerifyCsrfToken|ValidateCsrfToken)\b/', $contents) === 1) {
                $violations[] = str_replace('\\', '/', $file->getRelativePathname());
            }
        }
    }

    sort($violations);

    expect($violations)->toBe([]);
});

it('pins deployed cache redis and session identifiers across the upgrade', function (): void {
    $environment = File::get(base_path('.env.example'));

    expect($environment)->toContain('CACHE_PREFIX=conecta_hn_cache')
        ->and($environment)->toContain('REDIS_PREFIX=conecta_hn_database_')
        ->and($environment)->toContain('SESSION_COOKIE=conecta_hn_session');
});

it('serves the shared private storage root through one named disk only', function (): void {
    $filesystems = require base_path('config/filesystems.php');

    expect($filesystems['disks']['local']['root'])->toBe($filesystems['disks']['private']['root'])
        ->and($filesystems['disks']['local']['serve'] ?? false)->toBeFalse()
        ->and($filesystems['disks']['private']['serve'] ?? false)->toBeTrue();
});
