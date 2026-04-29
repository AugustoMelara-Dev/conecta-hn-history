<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class ProductionStorageGuardCommand extends Command
{
    protected $signature = 'storage:production-guard
        {--env= : Environment to validate, defaults to APP_ENV}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Fail release if production storage still points to local/ephemeral disks.';

    public function handle(): int
    {
        $environment = (string) ($this->option('env') ?: app()->environment());
        $isProduction = $environment === 'production';
        $defaultDisk = (string) config('filesystems.default', 'local');
        $selectedMediaDisk = (string) config('filesystems.media_disk', 'media');
        $selectedEvidenceDisk = (string) config('filesystems.payment_evidence_disk', $selectedMediaDisk);
        $s3Endpoint = (string) config('filesystems.disks.s3.endpoint', '');
        $mediaDriver = (string) config('filesystems.disks.media.driver', 'local');
        $mediaEndpoint = (string) config('filesystems.disks.media.endpoint', '');
        $r2Endpoint = (string) config('filesystems.disks.r2.endpoint', '');
        $defaultDiskIsDurable = $defaultDisk === 'r2'
            || ($defaultDisk === 's3' && $this->looksLikeDurableObjectStorageEndpoint($s3Endpoint))
            || ($defaultDisk === 'media' && $mediaDriver === 's3' && $this->looksLikeDurableObjectStorageEndpoint($mediaEndpoint ?: $s3Endpoint ?: $r2Endpoint));
        $mediaDiskIsDurable = $selectedMediaDisk === 'r2'
            || ($selectedMediaDisk === 'media' && $mediaDriver === 's3' && $this->looksLikeDurableObjectStorageEndpoint($mediaEndpoint ?: $s3Endpoint ?: $r2Endpoint));
        $evidenceDiskIsDurable = $selectedEvidenceDisk === 'r2'
            || ($selectedEvidenceDisk === 'media' && $mediaDiskIsDurable);
        $violations = [];

        if ($isProduction && ! $defaultDiskIsDurable) {
            $violations[] = [
                'key' => 'FILESYSTEM_DISK',
                'actual' => $defaultDisk,
                'expected' => 'r2, media, or s3 backed by approved durable object storage',
                'reason' => 'Production uploads and generated files must use durable S3-compatible object storage instead of local or generic/unknown disks.',
            ];
        }

        if ($isProduction && ! $mediaDiskIsDurable) {
            $violations[] = [
                'key' => 'MEDIA_DISK or MEDIA_DISK_DRIVER',
                'actual' => "{$selectedMediaDisk} ({$mediaDriver})",
                'expected' => 'MEDIA_DISK=r2 or MEDIA_DISK=media with MEDIA_DISK_DRIVER=s3 and approved durable endpoint',
                'reason' => 'Tenant/product images must survive redeploys and container restarts.',
            ];
        }

        if ($isProduction && ! $evidenceDiskIsDurable) {
            $violations[] = [
                'key' => 'PAYMENT_EVIDENCE_DISK',
                'actual' => "{$selectedEvidenceDisk} ({$mediaDriver})",
                'expected' => 'PAYMENT_EVIDENCE_DISK=r2 or media backed by approved durable object storage',
                'reason' => 'Payment proofs and receipts are audit evidence and must not be stored on ephemeral disk.',
            ];
        }

        $payload = [
            'status' => $violations === [] ? 'ok' : 'failed',
            'environment' => $environment,
            'default_disk' => $defaultDisk,
            'media_disk' => $selectedMediaDisk,
            'payment_evidence_disk' => $selectedEvidenceDisk,
            'media_driver' => $mediaDriver,
            's3_endpoint' => $s3Endpoint,
            'media_endpoint' => $mediaEndpoint,
            'violations' => $violations,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($violations === []) {
            $this->info('Production storage guard passed.');
        } else {
            $this->error('Production storage guard failed.');

            foreach ($violations as $violation) {
                $this->line("- {$violation['key']}: {$violation['actual']} expected {$violation['expected']}. {$violation['reason']}");
            }
        }

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }

    private function looksLikeDurableObjectStorageEndpoint(string $endpoint): bool
    {
        $endpoint = strtolower(trim($endpoint));

        if ($endpoint === '' || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return str_contains($endpoint, '.r2.cloudflarestorage.com')
            || str_contains($endpoint, 'storageapi.dev')
            || str_contains($endpoint, 'storage.railway.app');
    }
}
