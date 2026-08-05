<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class StorageProductionGuardTest extends TestCase
{
    public function test_it_fails_closed_when_production_uses_ephemeral_or_publicly_unbounded_disks(): void
    {
        config()->set([
            'filesystems.default' => 'public',
            'filesystems.disks.local' => ['driver' => 'local'],
            'filesystems.disks.private' => ['driver' => 'local'],
            'filesystems.disks.public' => ['driver' => 'local', 'url' => 'http://assets.example.test'],
            'filesystems.disks.media' => ['driver' => 's3', 'bucket' => null, 'url' => null],
        ]);

        $exitCode = Artisan::call('storage:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $keys = array_column($payload['violations'], 'key');

        self::assertSame(1, $exitCode);
        self::assertSame('failed', $payload['status']);
        self::assertContains('LOCAL_DISK_DRIVER', $keys);
        self::assertContains('LOCAL_DISK_VISIBILITY', $keys);
        self::assertContains('LOCAL_DISK_THROW', $keys);
        self::assertContains('LOCAL_DISK_REPORT', $keys);
        self::assertContains('PRIVATE_DISK_DRIVER', $keys);
        self::assertContains('PUBLIC_DISK_URL', $keys);
        self::assertContains('MEDIA_DISK_BUCKET', $keys);
        self::assertContains('FILESYSTEM_DISK', $keys);
    }

    public function test_it_accepts_the_managed_s3_and_cloudfront_contract_without_static_keys(): void
    {
        $private = [
            'driver' => 's3',
            'bucket' => 'conecta-private',
            'endpoint' => null,
            'use_path_style_endpoint' => false,
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
        ];
        $published = [
            'driver' => 's3',
            'bucket' => 'conecta-published',
            'url' => 'https://assets.conecta.example',
            'endpoint' => null,
            'use_path_style_endpoint' => false,
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
        ];

        config()->set([
            'filesystems.default' => 'private',
            'filesystems.disks.local' => $private,
            'filesystems.disks.private' => $private,
            'filesystems.disks.public' => $published,
            'filesystems.disks.media' => $published,
        ]);

        $exitCode = Artisan::call('storage:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $exitCode);
        self::assertSame('ok', $payload['status']);
        self::assertSame([], $payload['violations']);
    }
}
