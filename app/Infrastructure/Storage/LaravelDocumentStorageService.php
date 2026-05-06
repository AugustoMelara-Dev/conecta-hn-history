<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * LaravelDocumentStorageService - Infrastructure Adapter for Document Storage
 *
 * PERMISSION GRANTED: This class is the ONLY place allowed to use Storage facade.
 * All Application/Domain code MUST go through the Interface.
 */
final class LaravelDocumentStorageService implements DocumentStorageServiceInterface
{
    private const DISK = 'public'; // Or 's3' for production

    private const BASE_PATH = 'payments/receipts';

    /**
     * {@inheritDoc}
     */
    public function storeProofOfPayment(string $sourcePath, string $paymentReference): string
    {
        // Generate a unique, organized path
        $year = now()->format('Y');
        $month = now()->format('m');
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'pdf';
        $sanitizedRef = Str::slug($paymentReference);

        $destinationPath = sprintf(
            '%s/%s/%s/%s_%s.%s',
            self::BASE_PATH,
            $year,
            $month,
            $sanitizedRef,
            Str::random(8),
            $extension
        );

        // Move file from temporary location to permanent storage
        // If sourcePath is already on the same disk, use move. Otherwise, copy and delete.
        if (Storage::disk(self::DISK)->exists($sourcePath)) {
            // File is already on target disk, just move it
            Storage::disk(self::DISK)->move($sourcePath, $destinationPath);
        } else {
            // File is on local filesystem (from upload), copy to storage
            $contents = file_get_contents($sourcePath);
            Storage::disk(self::DISK)->put($destinationPath, $contents);
            // Optionally delete temp file
            @unlink($sourcePath);
        }

        return $destinationPath;
    }

    /**
     * {@inheritDoc}
     */
    public function promote(string $tempPath): string
    {
        // For now, treat storeProofOfPayment as direct-to-permanent, so promote is a no-op loopback.
        // In a strict refactor, store would save to /temp and this would move to /permanent.
        return $tempPath;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteProofOfPayment(string $storagePath): bool
    {
        return Storage::disk(self::DISK)->delete($storagePath);
    }

    public function verifyPresence(string $path): void
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            throw \App\Domain\Billing\Exceptions\PaymentEvidenceNotFoundException::atPath($path);
        }

        if ($disk->size($path) === 0) {
            throw \App\Domain\Billing\Exceptions\PaymentEvidenceNotFoundException::checkSumFailed($path);
        }
    }
}
