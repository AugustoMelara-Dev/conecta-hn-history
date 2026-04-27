<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Shared\Contracts\MediaStorageServiceInterface;
use App\Domain\Shared\DTOs\FileTransferObject;
use Illuminate\Support\Facades\Storage;

/**
 * LaravelMediaStorageService - Infrastructure Adapter for Media Storage
 *
 * Uses Laravel's Storage facade with the configured media disk.
 */
final class LaravelMediaStorageService implements MediaStorageServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function storeLogo(string $tenantId, FileTransferObject $file): string
    {
        return $this->storeFile($tenantId, 'logos', $file);
    }

    /**
     * {@inheritDoc}
     */
    public function storeBanner(string $tenantId, FileTransferObject $file): string
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

        return Storage::disk($this->disk())->delete($path);
    }

    /**
     * Store file with consistent naming.
     */
    private function storeFile(string $tenantId, string $subfolder, FileTransferObject $file): string
    {
        $directory = "tenants/{$tenantId}/{$subfolder}";
        $filename = time().'_'.$file->originalName;

        Storage::disk($this->disk())->putFileAs(
            $directory,
            $file->tempPath,
            $filename
        );

        return "{$directory}/{$filename}";
    }

    private function disk(): string
    {
        return (string) config('filesystems.media_disk', env('MEDIA_DISK', 'media'));
    }
}
