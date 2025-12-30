<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use Illuminate\Http\UploadedFile;

/**
 * FileStorage Contract - Memory-Safe Cloud-Agnostic File Operations
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Big Data Resilience for Cloud Storage         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PROBLEM: Returning arrays from file listings causes OOM/Timeout.       ║
 * ║    S3 bucket with 500k files → 500 paginated API calls → 30s timeout   ║
 * ║    Even if completed: 500k string paths = 50MB+ RAM spike              ║
 * ║                                                                          ║
 * ║  WHY iterable INSTEAD OF array?                                         ║
 * ║                                                                          ║
 * ║    ┌─────────────────────────────────────────────────────────────────┐   ║
 * ║    │  WRONG: files(): array                                          │   ║
 * ║    │  S3 API Page 1 (1000 files)                                     │   ║
 * ║    │  S3 API Page 2 (1000 files)                                     │   ║
 * ║    │  ... wait for 500 pages ...                                     │   ║
 * ║    │  S3 API Page 500 (1000 files)                                   │   ║
 * ║    │  FINALLY return array of 500,000 strings                        │   ║
 * ║    │  ↓ TIMEOUT or OOM before first file processed                   │   ║
 * ║    └─────────────────────────────────────────────────────────────────┘   ║
 * ║                                                                          ║
 * ║    ┌─────────────────────────────────────────────────────────────────┐   ║
 * ║    │  CORRECT: files(): iterable (Generator)                         │   ║
 * ║    │  S3 API Page 1 → yield file1, yield file2, ... yield file1000   │   ║
 * ║    │  ↓ Consumer processes file1 IMMEDIATELY                         │   ║
 * ║    │  S3 API Page 2 → yield file1001, ...                            │   ║
 * ║    │  ↓ Consumer already processing while page 2 loads               │   ║
 * ║    │  ↓ No timeout, constant ~5MB RAM                                │   ║
 * ║    └─────────────────────────────────────────────────────────────────┘   ║
 * ║                                                                          ║
 * ║  TENANT SCOPING:                                                        ║
 * ║    All paths are automatically prefixed with tenant ID.                ║
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
     * @param iterable<string> $paths Can be array or Generator
     */
    public function deleteMany(iterable $paths): bool;

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
     * Stream all files in a directory.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  @notice MEMORY SAFETY: Returns iterable (Generator), NOT array       ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  WHY NOT return array?                                                ║
     * ║    On S3 with 500k files, returning array requires:                   ║
     * ║    1. Make 500 paginated API calls (500ms each = 250 seconds)        ║
     * ║    2. Accumulate 500k strings in memory (50MB+)                      ║
     * ║    3. Only THEN return control to caller                             ║
     * ║    Result: Timeout or OOM before processing starts                    ║
     * ║                                                                        ║
     * ║  iterable (Generator) allows:                                         ║
     * ║    - Fetch page 1, yield immediately                                 ║
     * ║    - Caller processes while page 2 loads in background               ║
     * ║    - Never more than 1000 paths in memory at once                    ║
     * ║                                                                        ║
     * ║  USAGE:                                                               ║
     * ║    foreach ($storage->files('uploads') as $path) {                   ║
     * ║        processFile($path); // Starts immediately, no wait            ║
     * ║    }                                                                  ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     *
     * @param string $directory
     * @param bool $recursive
     * @return iterable<int, string> Generator that yields file paths one at a time
     */
    public function files(string $directory = '', bool $recursive = false): iterable;

    /**
     * Stream all directories in a path.
     *
     * @notice Same memory-safety rationale as files().
     *
     * @param string $directory
     * @param bool $recursive
     * @return iterable<int, string>
     */
    public function directories(string $directory = '', bool $recursive = false): iterable;

    /**
     * Get file metadata without loading content.
     *
     * @param string $path
     * @return array{size: int, last_modified: int, mime_type: string}
     */
    public function metadata(string $path): array;
}
