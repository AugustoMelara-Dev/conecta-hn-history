<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Shared\Contracts\MediaStorageServiceInterface;
use App\Domain\Shared\DTOs\FileTransferObject;
use Illuminate\Support\Facades\Storage;

/**
 * LaravelMediaStorageService - Infrastructure Adapter for Media Storage
 *
 * Uses Laravel's Storage facade with the 'public' disk.
 */
final class LaravelMediaStorageService implements MediaStorageServiceInterface
{
    private const DISK = 'public';

    /**
     * {@inheritDoc}
     */
    public function storeLogo(int $tenantId, FileTransferObject $file): string
    {
        return $this->storeFile($tenantId, 'logos', $file);
    }

    /**
     * {@inheritDoc}
     */
    public function storeBanner(int $tenantId, FileTransferObject $file): string
    {
        return $this->storeFile($tenantId, 'banners', $file);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $path): bool
    {
        if (empty($path)) {
            return true;
        }

        return Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Store file with consistent naming.
     */
    private function storeFile(int $tenantId, string $subfolder, FileTransferObject $file): string
    {
        $directory = "tenants/{$tenantId}/{$subfolder}";
        $filename = time().'_'.$file->originalName;

        Storage::disk(self::DISK)->putFileAs(
            $directory,
            $file->tempPath,
            $filename
        );

        return "{$directory}/{$filename}";
    }
}
