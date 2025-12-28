<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ReportDownloadController - Signed URL File Server
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURE FILE SERVING: Validates signed URLs before serving files        ║
 * ║  LOCAL STORAGE: Works without cloud providers (S3, Azure)               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * WHY THIS CONTROLLER EXISTS:
 * Laravel's Storage::temporaryUrl() only works with cloud drivers (S3, Azure).
 * For local disk storage, we must use Laravel's URL::temporarySignedRoute()
 * combined with a controller that validates the signature and serves the file.
 *
 * SECURITY MODEL:
 * 1. Job generates file and creates signed URL with expiration
 * 2. User receives link via notification
 * 3. This controller validates signature before serving
 * 4. File served via streaming (memory-efficient)
 */
class ReportDownloadController extends Controller
{
    /**
     * Download a report file via signed URL.
     *
     * GET /api/reports/download/{path}?signature=xxx&expires=xxx
     *
     * SECURITY:
     * - URL signature verified by Laravel's 'signed' middleware
     * - Path is validated to prevent directory traversal
     * - Only files in 'reports/' directory allowed
     */
    public function download(Request $request, string $path): StreamedResponse
    {
        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY: Validate path to prevent directory traversal attacks
        // ═══════════════════════════════════════════════════════════════════════
        $safePath = $this->validateAndSanitizePath($path);

        if (! $safePath) {
            abort(403, 'Ruta de archivo inválida.');
        }

        // ═══════════════════════════════════════════════════════════════════════
        // EXISTENCE CHECK
        // ═══════════════════════════════════════════════════════════════════════
        if (! Storage::disk('local')->exists($safePath)) {
            abort(404, 'El archivo no existe o ha expirado.');
        }

        // ═══════════════════════════════════════════════════════════════════════
        // SERVE FILE via streaming (memory-efficient for large files)
        // ═══════════════════════════════════════════════════════════════════════
        $filename = basename($safePath);
        $mimeType = $this->getMimeType($safePath);

        return Storage::disk('local')->download($safePath, $filename, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Validate and sanitize the file path.
     *
     * SECURITY:
     * - Only allows files in 'reports/' directory
     * - Blocks directory traversal (../, ..\)
     * - Blocks absolute paths
     */
    protected function validateAndSanitizePath(string $path): ?string
    {
        // Decode URL-encoded path
        $path = urldecode($path);

        // Block directory traversal attempts
        if (str_contains($path, '..') || str_contains($path, '\\..')) {
            return null;
        }

        // Block absolute paths
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path)) {
            return null;
        }

        // Ensure path starts with 'reports/'
        if (! str_starts_with($path, 'reports/')) {
            return null;
        }

        // Validate extension (only allow report formats)
        $allowedExtensions = ['pdf', 'xlsx', 'csv'];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, $allowedExtensions, true)) {
            return null;
        }

        return $path;
    }

    /**
     * Get MIME type for file.
     */
    protected function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
}
