<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SupportedRuntimePolicyTest extends TestCase
{
    #[Test]
    public function manifests_and_execution_images_target_one_supported_runtime(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $package = json_decode((string) file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
        $docker = (string) file_get_contents(base_path('Dockerfile'));
        $ci = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

        self::assertSame('^8.4', $composer['require']['php']);
        self::assertSame('8.4.0', $composer['config']['platform']['php']);
        self::assertSame('^13.0', $composer['require']['laravel/framework']);
        self::assertSame('22.x', $package['engines']['node']);
        self::assertStringContainsString('frankenphp:1-php8.4-bookworm', $docker);
        self::assertStringContainsString('node:22.13-bookworm-slim', $docker);
        self::assertStringContainsString('php-version: "8.4"', $ci);
        self::assertStringContainsString('node-version: "22.13"', $ci);
    }

    #[Test]
    public function supported_test_toolchain_is_not_silently_downgraded(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

        self::assertStringStartsWith('^4.', $composer['require-dev']['pestphp/pest']);
        self::assertStringStartsWith('^4.', $composer['require-dev']['pestphp/pest-plugin-laravel']);
        self::assertStringStartsWith('^12.', $composer['require-dev']['phpunit/phpunit']);
    }
}
