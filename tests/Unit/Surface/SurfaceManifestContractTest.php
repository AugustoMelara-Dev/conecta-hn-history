<?php

declare(strict_types=1);

namespace Tests\Unit\Surface;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SurfaceManifestContractTest extends TestCase
{
    #[Test]
    public function manifest_defines_three_split_surfaces_and_legacy_paths(): void
    {
        $manifestPath = base_path('apps/surface-manifest.v1.json');
        self::assertFileExists($manifestPath);

        $raw = file_get_contents($manifestPath);
        self::assertNotFalse($raw);

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('surface-manifest-v1', data_get($manifest, 'version'));
        self::assertArrayHasKey('user_app', (array) data_get($manifest, 'surfaces', []));
        self::assertArrayHasKey('business_app', (array) data_get($manifest, 'surfaces', []));
        self::assertArrayHasKey('platform_api', (array) data_get($manifest, 'surfaces', []));

        $legacyPaths = (array) data_get($manifest, 'compatibility.legacy_paths', []);
        self::assertContains('/dashboard', $legacyPaths);
        self::assertContains('/workspace/dashboard', $legacyPaths);
        self::assertContains('/workspace/marketing', $legacyPaths);
        self::assertContains('/workspace/reports', $legacyPaths);
        self::assertContains('/workspace/upgrade', $legacyPaths);
        self::assertContains('/workspace/landing-page', $legacyPaths);
    }
}
