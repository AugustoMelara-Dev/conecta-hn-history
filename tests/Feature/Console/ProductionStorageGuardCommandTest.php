<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ProductionStorageGuardCommandTest extends TestCase
{
    public function test_it_fails_production_when_storage_is_not_r2_backed(): void
    {
        config([
            'filesystems.default' => 'local',
            'filesystems.media_disk' => 'media',
            'filesystems.payment_evidence_disk' => 'media',
            'filesystems.disks.media.driver' => 'local',
            'filesystems.disks.media.endpoint' => '',
        ]);

        $exitCode = Artisan::call('storage:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $violationKeys = array_column($payload['violations'], 'key');

        $this->assertSame(1, $exitCode);
        $this->assertSame('failed', $payload['status']);
        $this->assertContains('FILESYSTEM_DISK', $violationKeys);
        $this->assertContains('MEDIA_DISK or MEDIA_DISK_DRIVER', $violationKeys);
        $this->assertContains('PAYMENT_EVIDENCE_DISK', $violationKeys);
    }

    public function test_it_passes_production_when_media_and_evidence_use_r2(): void
    {
        config([
            'filesystems.default' => 'r2',
            'filesystems.media_disk' => 'media',
            'filesystems.payment_evidence_disk' => 'media',
            'filesystems.disks.media.driver' => 's3',
            'filesystems.disks.media.endpoint' => 'https://abc123.r2.cloudflarestorage.com',
        ]);

        $exitCode = Artisan::call('storage:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame([], $payload['violations']);
    }

    public function test_it_passes_production_when_media_and_evidence_use_railway_bucket_storage(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.media_disk' => 'media',
            'filesystems.payment_evidence_disk' => 'media',
            'filesystems.disks.s3.endpoint' => 'https://t3.storageapi.dev',
            'filesystems.disks.media.driver' => 's3',
            'filesystems.disks.media.endpoint' => 'https://t3.storageapi.dev',
        ]);

        $exitCode = Artisan::call('storage:production-guard', [
            '--env' => 'production',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame([], $payload['violations']);
    }

    public function test_it_allows_local_development_storage(): void
    {
        config([
            'filesystems.default' => 'local',
            'filesystems.media_disk' => 'media',
            'filesystems.payment_evidence_disk' => 'media',
            'filesystems.disks.media.driver' => 'local',
        ]);

        $exitCode = Artisan::call('storage:production-guard', [
            '--env' => 'local',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
    }
}
