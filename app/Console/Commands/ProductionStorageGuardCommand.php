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
        $allowedDurableDisks = ['r2'];
        $defaultDisk = (string) config('filesystems.default', 'local');
        $selectedMediaDisk = (string) config('filesystems.media_disk', 'media');
        $selectedEvidenceDisk = (string) config('filesystems.payment_evidence_disk', $selectedMediaDisk);
        $mediaDriver = (string) config('filesystems.disks.media.driver', 'local');
        $mediaEndpoint = (string) config('filesystems.disks.media.endpoint', '');
        $r2Endpoint = (string) config('filesystems.disks.r2.endpoint', '');
        $mediaDiskIsDurable = $selectedMediaDisk === 'r2'
            || ($selectedMediaDisk === 'media' && $mediaDriver === 's3' && $this->looksLikeR2Endpoint($mediaEndpoint ?: $r2Endpoint));
        $evidenceDiskIsDurable = $selectedEvidenceDisk === 'r2'
            || ($selectedEvidenceDisk === 'media' && $mediaDiskIsDurable);
        $violations = [];

        if ($isProduction && ! in_array($defaultDisk, $allowedDurableDisks, true)) {
            $violations[] = [
                'key' => 'FILESYSTEM_DISK',
                'actual' => $defaultDisk,
                'expected' => 'r2',
                'reason' => 'Production uploads and generated files must use Cloudflare R2 to avoid ephemeral disk and unpredictable egress costs.',
            ];
        }

        if ($isProduction && ! $mediaDiskIsDurable) {
            $violations[] = [
                'key' => 'MEDIA_DISK or MEDIA_DISK_DRIVER',
                'actual' => "{$selectedMediaDisk} ({$mediaDriver})",
                'expected' => 'MEDIA_DISK=r2 or MEDIA_DISK=media with MEDIA_DISK_DRIVER=s3 and R2 endpoint',
                'reason' => 'Tenant/product images must survive redeploys and container restarts.',
            ];
        }

        if ($isProduction && ! $evidenceDiskIsDurable) {
            $violations[] = [
                'key' => 'PAYMENT_EVIDENCE_DISK',
                'actual' => "{$selectedEvidenceDisk} ({$mediaDriver})",
                'expected' => 'PAYMENT_EVIDENCE_DISK=r2 or media backed by R2',
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

    private function looksLikeR2Endpoint(string $endpoint): bool
    {
        $endpoint = strtolower(trim($endpoint));

        return $endpoint !== ''
            && str_contains($endpoint, '.r2.cloudflarestorage.com')
            && filter_var($endpoint, FILTER_VALIDATE_URL) !== false;
    }
}
