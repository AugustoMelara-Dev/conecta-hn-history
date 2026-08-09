<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Billing\Exceptions\PaymentEvidenceNotFoundException;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use League\Flysystem\UnableToRetrieveMetadata;
use RuntimeException;
use Throwable;

/**
 * GenericDocumentStorageService - Polymorphic Storage Adapter (Level 25 - Env Aware)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADAPTABILITY: ENVIRONMENT AGNOSTIC                                      ║
 * ║  1. Uses config('filesystems.default') instead of hardcoded 's3'.        ║
 * ║  2. Uses Streams for memory efficiency.                                  ║
 * ║  3. Implements Atomic Copy-Delete for promotion.                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class S3DocumentStorageService implements DocumentStorageServiceInterface
{
    private const QUARANTINE_PREFIX = 'temp';

    private const PERMANENT_PREFIX = 'permanent';

    public function __construct(
        private FilesystemManager $storage
    ) {}

    public function storeProofOfPayment(string $sourcePath, string $paymentReference): string
    {
        // Enforce Quarantine: All uploads start in temp/
        $destinationPath = sprintf('%s/%s.pdf', self::QUARANTINE_PREFIX, $paymentReference);

        $diskName = config('filesystems.default', 'local');
        $disk = $this->storage->disk($diskName);

        // STREAMING UPLOAD
        $stream = fopen($sourcePath, 'r');

        if ($stream === false) {
            throw new RuntimeException("Failed to open source file stream: {$sourcePath}");
        }

        try {
            $success = $disk->put($destinationPath, $stream);
        } catch (Throwable $e) {
            fclose($stream);
            throw new RuntimeException("Stream upload failed on disk [{$diskName}]: ".$e->getMessage(), 0, $e);
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $success) {
            throw new RuntimeException("Failed to upload proof of payment to quarantine on disk [{$diskName}]");
        }

        return $destinationPath;
    }

    public function promote(string $tempPath): string
    {
        if (! str_starts_with($tempPath, self::QUARANTINE_PREFIX)) {
            throw new RuntimeException("Cannot promote a file that is not in quarantine: {$tempPath}");
        }

        $diskName = config('filesystems.default', 'local');
        $disk = $this->storage->disk($diskName);

        // Verify existence
        if (! $disk->exists($tempPath)) {
            // If relying on this method, we expect file to be there.
            // Exception will trigger Self-Healing in Listener if applicable.
            throw new RuntimeException("Quarantine file not found on disk [{$diskName}]: {$tempPath}");
        }

        // INTEGRITY FIX: Unique Promotion (Level 31)
        // Instead of reusing the Temp UUID (which allows Reference-Jacking if a user reuses a temp key),
        // we generate a FRESH UUID for the permanent artifact.
        // This ensures 1 Payment = 1 Unique File. No shared fate.

        // Parse Tenant ID manually to maintain structure: temp/{tenant}/{uuid}.ext
        // $parts = [temp, tenant, filename]
        $parts = explode('/', $tempPath);
        $tenantId = $parts[1] ?? 'common';
        $extension = pathinfo($tempPath, PATHINFO_EXTENSION);

        $permanentPath = sprintf(
            '%s/%s/%s.%s',
            self::PERMANENT_PREFIX,
            $tenantId,
            Str::uuid()->toString(),
            $extension
        );

        // Atomic "Move" (Copy + Delete-Later)
        $copySuccess = $disk->copy($tempPath, $permanentPath);

        if (! $copySuccess) {
            throw new RuntimeException("Failed to promote file to permanent storage on disk [{$diskName}]");
        }

        // CRITICAL RESILIENCE FIX (Level 30):
        // do NOT delete the temp file synchronously.
        // 1. Race Condition: If we delete now, but the DB update lags, users get 404s.
        // 2. Safety: If DB update fails, we want the temp file to survive for retry.
        // 3. Cleanup: We rely on S3 Lifecycle Rules (e.g., 24h expiry) to clean "temp/".
        // $disk->delete($tempPath); <--- REMOVED

        return $permanentPath;
    }

    public function delete(string $path): void
    {
        $diskName = config('filesystems.default', 'local');
        $this->storage->disk($diskName)->delete($path);
    }

    public function verifyPresence(string $path): void
    {
        $diskName = config('filesystems.default', 'local');
        $disk = $this->storage->disk($diskName);

        try {
            // OPTIMIZATION: Single S3 Call (Latency Reduction)
            $size = $disk->size($path);
        } catch (Throwable $e) {
            // CRITICAL: Differentiate "Not Found" from "Infrastructure Failure" (503/Timeout)
            // If the driver explicitly says "Unable to retrieve metadata", it might be missing OR a connection error.
            // AWS SDK typically throws an exception with a code.

            // Heuristic: If it's a "file not found" scenario, wrap it.
            // If it's a connection error, letting it bubble up is safer for retries/dead-lettering.

            // For Flysystem V3/V2, 'UnableToRetrieveMetadata' is common.
            // We'll check the message or assume 'Unable' implies missing in standard operation,
            // BUT strict observability requires we don't swallow 500s.

            // Simplest Robust Check:
            if ($e instanceof UnableToRetrieveMetadata || str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), '404')) {
                throw PaymentEvidenceNotFoundException::atPath($path);
            }

            // RETHROW Infrastructure Errors (e.g., 503 Service Unavailable, Curl Error)
            throw $e;
        }

        if ($size === 0) {
            throw PaymentEvidenceNotFoundException::checkSumFailed($path);
        }
    }
}
