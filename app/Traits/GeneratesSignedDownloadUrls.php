<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\URL;

/**
 * GeneratesSignedDownloadUrls - Helper for Local Storage File Downloads
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DRIVER AGNOSTIC: Works with local disk OR cloud storage (S3/Azure)     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * WHY THIS TRAIT EXISTS:
 * Laravel's Storage::temporaryUrl() only works with cloud drivers.
 * For local disk, we use Laravel's URL::temporarySignedRoute() instead.
 *
 * USAGE:
 * 1. Use this trait in your Job class
 * 2. Call $this->generateDownloadUrl($filename, $expiresAt)
 * 3. The URL will work regardless of storage driver
 */
trait GeneratesSignedDownloadUrls
{
    /**
     * Generate a secure download URL for a stored file.
     *
     * @param string $filename Path to file in storage
     * @param \DateTimeInterface $expiresAt When the URL should expire
     * @return string Signed download URL
     */
    protected function generateDownloadUrl(string $filename, \DateTimeInterface $expiresAt): string
    {
        // ═══════════════════════════════════════════════════════════════════════
        // SIGNED ROUTE: Works with local disk (no cloud dependency)
        // ═══════════════════════════════════════════════════════════════════════
        // This generates a URL like:
        // /api/reports/download/reports%2Ftenant-slug%2Fsales_uuid.pdf?expires=xxx&signature=xxx
        //
        // When the user clicks this URL:
        // 1. Laravel's 'signed' middleware validates the signature
        // 2. ReportDownloadController serves the file
        // 3. File is streamed (memory-efficient)

        return URL::temporarySignedRoute(
            'reports.download',          // Route name
            $expiresAt,                   // Expiration time
            ['path' => $filename]         // Path parameter
        );
    }
}
