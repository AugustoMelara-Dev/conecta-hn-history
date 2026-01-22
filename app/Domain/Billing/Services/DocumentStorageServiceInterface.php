<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

interface DocumentStorageServiceInterface
{
    /**
     * Store the proof of payment in a temporary 'quarantine' location.
     * The file will be auto-deleted by S3 Lifecycle if not promoted.
     *
     * @param  string  $sourcePath  Local path to the uploaded file
     * @param  string  $paymentReference  Unique reference for naming
     * @return string The temporary path in storage (e.g. temp/123.pdf)
     */
    public function storeProofOfPayment(string $sourcePath, string $paymentReference): string;

    /**
     * Promote a file from temporary storage to permanent storage.
     * Ideally keeps the same name but moves prefix from temp/ to permanent/.
     *
     * @param  string  $tempPath  The path returned by storeProofOfPayment
     * @return string The new permanent path
     */
    public function promote(string $tempPath): string;

    /**
     * Delete a file from storage.
     */
    public function delete(string $path): void;

    /**
     * Verify that a file exists and has content (Level 30).
     *
     * @param  string  $path  The storage key to check
     *
     * @throws \RuntimeException If file is missing or empty
     */
    public function verifyPresence(string $path): void;
}
