<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Shared\Contracts\MediaStorageServiceInterface;
use App\Domain\Shared\DTOs\FileTransferObject;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
    public function delete(string $tenantId, string $path): bool
    {
        if (empty($path)) {
            return true;
        }

        $this->assertTenantPath($tenantId, $path);

        return Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Store file with consistent naming.
     */
    private function storeFile(string $tenantId, string $subfolder, FileTransferObject $file): string
    {
        $this->assertTenantId($tenantId);

        $directory = "tenants/{$tenantId}/{$subfolder}";
        $extension = strtolower($file->getExtension());
        $filename = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');

        Storage::disk(self::DISK)->putFileAs(
            $directory,
            $file->tempPath,
            $filename
        );

        return "{$directory}/{$filename}";
    }

    private function assertTenantPath(string $tenantId, string $path): void
    {
        $this->assertTenantId($tenantId);
        $normalizedPath = str_replace('\\', '/', trim($path));
        $expectedPrefix = "tenants/{$tenantId}/";

        if (
            $normalizedPath !== $path
            || ! str_starts_with($normalizedPath, $expectedPrefix)
            || str_contains($normalizedPath, '..')
            || preg_match('/[[:cntrl:]]/', $normalizedPath) === 1
        ) {
            throw new InvalidArgumentException('Media path does not belong to the tenant boundary.');
        }
    }

    private function assertTenantId(string $tenantId): void
    {
        if (! Str::isUuid($tenantId)) {
            throw new InvalidArgumentException('Media tenant identifier must be a UUID.');
        }
    }
}
