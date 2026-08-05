<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

use App\Domain\Shared\DTOs\FileTransferObject;

/**
 * MediaStorageServiceInterface - Domain Port for Media Storage
 *
 * Abstracts file storage operations from infrastructure concerns.
 * Implementations may use local disk, S3, Cloudinary, etc.
 */
interface MediaStorageServiceInterface
{
    /**
     * Store a logo file for a tenant.
     *
     * @return string The stored file path
     */
    public function storeLogo(string $tenantId, FileTransferObject $file): string;

    /**
     * Store a banner/cover file for a tenant.
     *
     * @return string The stored file path
     */
    public function storeBanner(string $tenantId, FileTransferObject $file): string;

    /**
     * Delete a file only when it belongs to the explicit tenant boundary.
     *
     * @return bool Success status
     */
    public function delete(string $tenantId, string $path): bool;
}
