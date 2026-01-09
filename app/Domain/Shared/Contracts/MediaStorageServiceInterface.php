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
     * @param int $tenantId
     * @param FileTransferObject $file
     * @return string The stored file path
     */
    public function storeLogo(int $tenantId, FileTransferObject $file): string;

    /**
     * Store a banner/cover file for a tenant.
     *
     * @param int $tenantId
     * @param FileTransferObject $file
     * @return string The stored file path
     */
    public function storeBanner(int $tenantId, FileTransferObject $file): string;

    /**
     * Delete a file by path.
     *
     * @param string $path
     * @return bool Success status
     */
    public function delete(string $path): bool;
}
