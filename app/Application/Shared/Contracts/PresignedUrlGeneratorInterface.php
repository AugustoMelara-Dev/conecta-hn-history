<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * PresignedUrlGeneratorInterface - Cloud Storage Upload Authorization Contract
 *
 * Abstracts the generation of pre-signed PUT URLs for secure direct uploads.
 * The Application Layer uses this contract; the Infrastructure Layer provides
 * the implementation (e.g., AWS S3, GCS, Azure Blob).
 */
interface PresignedUrlGeneratorInterface
{
    /**
     * Generate a pre-signed PUT URL for secure direct upload.
     *
     * @param  string  $key  The object key (path) in the storage backend
     * @param  string  $mimeType  The strictly enforced Content-Type
     * @param  int  $sizeBytes  The strictly enforced Content-Length
     * @return array{url: string, headers: array<string, string>}
     */
    public function generatePutUrl(string $key, string $mimeType, int $sizeBytes): array;
}
