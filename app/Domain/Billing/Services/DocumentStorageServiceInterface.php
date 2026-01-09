<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

/**
 * DocumentStorageServiceInterface - Domain Port for Document Storage
 *
 * This interface abstracts file storage operations from the Application layer.
 * Infrastructure implementations (S3, Local, Azure Blob) live in Infrastructure.
 */
interface DocumentStorageServiceInterface
{
    /**
     * Store a proof of payment document securely.
     *
     * @param string $sourcePath Temporary path where the file currently resides.
     * @param string $paymentReference Unique reference to associate with the document.
     * @return string The final storage path or URL where the document was saved.
     */
    public function storeProofOfPayment(string $sourcePath, string $paymentReference): string;
    
    /**
     * Delete a proof of payment document (for compensation/rollback).
     *
     * @param string $storagePath The path returned by storeProofOfPayment.
     * @return bool True if deleted, false otherwise.
     */
    public function deleteProofOfPayment(string $storagePath): bool;
}
