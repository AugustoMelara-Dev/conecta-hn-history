<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use Illuminate\Http\UploadedFile;

/**
 * FileStorage Contract - Abstraction for Multi-Provider File Storage
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Cloud-Agnostic File Operations               ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  THE PROBLEM: Direct Storage facade usage couples code to Laravel.      ║
 * ║    Storage::disk('s3')->put($path, $file);                             ║
 * ║                                                                          ║
 * ║  This works, but:                                                        ║
 * ║    - Can't easily swap to Cloudflare R2, Bunny CDN, or DigitalOcean    ║
 * ║    - Can't apply tenant-specific prefixing automatically               ║
 * ║    - Hard to mock in unit tests                                         ║
 * ║                                                                          ║
 * ║  THE SOLUTION: Abstract file operations behind a contract.             ║
 * ║    The implementation handles disk selection, path prefixing, CDN URLs. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface FileStorage
{
    /**
     * Store an uploaded file.
     *
     * @param UploadedFile $file The uploaded file
     * @param string $directory Target directory (tenant-scoped automatically)
     * @param array $options ['visibility' => 'public', 'disk' => 'override']
     * @return string The stored file's path
     */
    public function store(UploadedFile $file, string $directory = '', array $options = []): string;

    /**
     * Store raw file contents.
     *
     * @param string $path Relative path
     * @param string|resource $contents File contents or stream
     * @param array $options
     * @return bool
     */
    public function put(string $path, mixed $contents, array $options = []): bool;

    /**
     * Get the public URL for a file.
     *
     * @param string $path Relative path
     * @return string Full public URL (CDN-ready)
     */
    public function url(string $path): string;

    /**
     * Get a temporary signed URL for private files.
     *
     * @param string $path Relative path
     * @param int $expirationMinutes
     * @return string Signed URL
     */
    public function temporaryUrl(string $path, int $expirationMinutes = 60): string;

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Delete a file.
     */
    public function delete(string $path): bool;

    /**
     * Delete multiple files.
     *
     * @param array<string> $paths
     */
    public function deleteMany(array $paths): bool;

    /**
     * Get file size in bytes.
     */
    public function size(string $path): int;

    /**
     * Get file's last modification timestamp.
     */
    public function lastModified(string $path): int;

    /**
     * Copy a file to a new location.
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file to a new location.
     */
    public function move(string $from, string $to): bool;

    /**
     * List all files in a directory.
     *
     * @param string $directory
     * @param bool $recursive
     * @return array<string>
     */
    public function files(string $directory = '', bool $recursive = false): array;
}
