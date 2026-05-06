<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * MediaCleanupCommand - Production-Grade Storage Reconciliation Engine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL RISK ANALYSIS: N+1 Query Prevention                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Checking DB per file causes query explosion.                   ║
 * ║    500,000 files × 3 queries/file = 1,500,000 database queries           ║
 * ║    At 1ms/query = 25 minutes just for DB round-trips                     ║
 * ║                                                                          ║
 * ║  SOLUTION: Pre-load file registry into HashSet                           ║
 * ║    1. Load ALL known paths into PHP Set (O(n) once)                      ║
 * ║    2. Check each file against Set (O(1) lookup)                          ║
 * ║    Result: 500k files = 500k O(1) checks = ~1 second                     ║
 * ║                                                                          ║
 * ║  MEMORY SAFETY:                                                          ║
 * ║    500k paths × ~100 bytes/path = ~50MB RAM for the Set                  ║
 * ║    Acceptable tradeoff vs 1.5M queries                                   ║
 * ║                                                                          ║
 * ║  60-MINUTE SAFETY BUFFER:                                                ║
 * ║    Files modified < 60 min ago are NEVER deleted, preventing race        ║
 * ║    conditions during concurrent uploads.                                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   php artisan media:cleanup
 *   php artisan media:cleanup --dry-run
 *   php artisan media:cleanup --buffer-minutes=120
 *
 * SCHEDULING:
 *   Schedule::command('media:cleanup')->daily()->at('03:00');
 */
class MediaCleanupCommand extends Command
{
    protected $signature = 'media:cleanup
                            {--dry-run : Preview without deleting anything}
                            {--buffer-minutes=60 : Safety buffer for recent files}
                            {--disk=public : Storage disk to scan}';

    protected $description = 'Reconcile database and filesystem to remove orphaned media (production-grade)';

    protected int $dbOrphansDeleted = 0;

    protected int $fileOrphansDeleted = 0;

    protected int $bytesRecovered = 0;

    protected bool $dryRun = false;

    /**
     * Pre-loaded registry of known file paths (HashSet for O(1) lookup)
     *
     * @var array<string, bool>
     */
    protected array $knownPaths = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $bufferMinutes = (int) $this->option('buffer-minutes');
        $disk = $this->option('disk');

        $this->components->info('Media Cleanup - Production-Grade Reconciliation');
        $this->components->bulletList([
            "Disk: {$disk}",
            "Safety buffer: {$bufferMinutes} minutes",
            'Mode: '.($this->dryRun ? 'DRY RUN' : 'LIVE'),
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 0: Pre-load file registry (prevents N+1 query explosion)
        // ═══════════════════════════════════════════════════════════════════════
        $this->preloadFileRegistry();

        // Pass 1: Database Cleaning
        $this->cleanOrphanedDatabaseRecords($disk);

        // Pass 2: Filesystem Cleaning (Memory-Safe)
        $this->cleanOrphanedFilesMemorySafe($disk, $bufferMinutes);

        // Free memory
        $this->knownPaths = [];

        $this->newLine();
        $this->components->twoColumnDetail('DB orphans removed', (string) $this->dbOrphansDeleted);
        $this->components->twoColumnDetail('File orphans removed', (string) $this->fileOrphansDeleted);
        $this->components->twoColumnDetail('Storage recovered', $this->formatBytes($this->bytesRecovered));

        Log::info('MediaCleanupCommand completed', [
            'db_orphans' => $this->dbOrphansDeleted,
            'file_orphans' => $this->fileOrphansDeleted,
            'bytes_recovered' => $this->bytesRecovered,
            'dry_run' => $this->dryRun,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Pre-load all known file paths into a HashSet for O(1) lookups.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  N+1 QUERY PREVENTION                                                  ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  BEFORE: For each of 500k files, do 3 DB queries = 1.5M queries        ║
     * ║  AFTER:  Load all paths once (3 queries), check HashSet = O(1)         ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     */
    protected function preloadFileRegistry(): void
    {
        $this->components->task('Pre-loading file registry', function () {
            // Load media table paths
            if (class_exists(Media::class)) {
                Media::query()
                    ->select(['file_path', 'path'])
                    ->chunkById(1000, function ($records) {
                        foreach ($records as $record) {
                            if (! empty($record->file_path)) {
                                $this->knownPaths[$record->file_path] = true;
                            }
                            if (! empty($record->path)) {
                                $this->knownPaths[$record->path] = true;
                            }
                        }
                    });
            }

            // Load product images (using raw query for efficiency)
            try {
                DB::table('products')
                    ->select('image')
                    ->whereNotNull('image')
                    ->orderBy('id')
                    ->chunk(1000, function ($records) {
                        foreach ($records as $record) {
                            if (! empty($record->image)) {
                                $this->knownPaths[$record->image] = true;
                                // Handle JSON arrays of images
                                if (str_starts_with($record->image, '[')) {
                                    $images = json_decode($record->image, true) ?? [];
                                    foreach ($images as $img) {
                                        if (is_string($img)) {
                                            $this->knownPaths[$img] = true;
                                        }
                                    }
                                }
                            }
                        }
                    });
            } catch (\Exception $e) {
                // Products table might not exist
            }

            // Load tenant logos
            try {
                DB::table('tenants')
                    ->select('logo')
                    ->whereNotNull('logo')
                    ->orderBy('id')
                    ->chunk(1000, function ($records) {
                        foreach ($records as $record) {
                            if (! empty($record->logo)) {
                                $this->knownPaths[$record->logo] = true;
                            }
                        }
                    });
            } catch (\Exception $e) {
                // Tenants table might not exist
            }

            $count = count($this->knownPaths);
            $this->line("    Loaded {$count} known paths into registry");

            return true;
        });
    }

    /**
     * Pass 1: Remove database records that have no corresponding physical file.
     */
    protected function cleanOrphanedDatabaseRecords(string $disk): void
    {
        $this->components->task('Pass 1: Cleaning orphaned database records', function () use ($disk) {
            if (! class_exists(Media::class)) {
                return true;
            }

            Media::query()
                ->chunkById(100, function ($mediaRecords) use ($disk) {
                    foreach ($mediaRecords as $media) {
                        $filePath = $media->file_path ?? $media->path ?? null;

                        if (! $filePath || ! Storage::disk($disk)->exists($filePath)) {
                            if (! $this->dryRun) {
                                $media->delete();
                            }
                            $this->dbOrphansDeleted++;
                        }
                    }
                });

            return true;
        });
    }

    /**
     * Pass 2: Remove physical files without DB records using recursive scanning.
     */
    protected function cleanOrphanedFilesMemorySafe(string $disk, int $bufferMinutes): void
    {
        $this->components->task('Pass 2: Scanning filesystem', function () use ($disk, $bufferMinutes) {
            $storage = Storage::disk($disk);
            $cutoffTime = now()->subMinutes($bufferMinutes)->timestamp;

            // Process directories one at a time
            $directories = ['uploads', 'media', 'products', 'tenants', 'images'];

            foreach ($directories as $directory) {
                if (! $storage->exists($directory)) {
                    continue;
                }

                $this->line("    Scanning: {$directory}/");
                $this->scanDirectoryRecursively($storage, $directory, $cutoffTime);
            }

            return true;
        });
    }

    /**
     * Recursively scan directory using subdirectory batching.
     */
    protected function scanDirectoryRecursively($storage, string $directory, int $cutoffTime): void
    {
        try {
            // Get files in current directory only (not recursive)
            $files = $storage->files($directory);

            foreach ($files as $file) {
                $this->processOrphanedFile($storage, $file, $cutoffTime);
            }

            // Free the files array
            unset($files);

            // Now get subdirectories and recurse
            $subdirectories = $storage->directories($directory);

            foreach ($subdirectories as $subdir) {
                $this->scanDirectoryRecursively($storage, $subdir, $cutoffTime);
            }

            // Free subdirectories array
            unset($subdirectories);

        } catch (\Exception $e) {
            Log::warning('MediaCleanup: Error scanning directory', [
                'directory' => $directory,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process a single file for orphan detection.
     * Uses O(1) HashSet lookup instead of N+1 DB queries.
     */
    protected function processOrphanedFile($storage, string $file, int $cutoffTime): void
    {
        try {
            $lastModified = $storage->lastModified($file);

            // ═══════════════════════════════════════════════════════════════════════
            // 60-MINUTE SAFETY BUFFER
            // ═══════════════════════════════════════════════════════════════════════
            if ($lastModified > $cutoffTime) {
                return;
            }

            // O(1) HashSet lookup instead of 3 DB queries
            if (! $this->isKnownPath($file)) {
                $fileSize = $storage->size($file);

                if ($this->dryRun) {
                    $this->line("      Would delete: {$file}");
                } else {
                    $storage->delete($file);
                }

                $this->fileOrphansDeleted++;
                $this->bytesRecovered += $fileSize;
            }
        } catch (\Exception $e) {
            // Silently skip problematic files
        }
    }

    /**
     * O(1) check if a file path exists in the pre-loaded registry.
     */
    protected function isKnownPath(string $filePath): bool
    {
        // Direct lookup
        if (isset($this->knownPaths[$filePath])) {
            return true;
        }

        // Check with storage prefix variations
        $variations = [
            $filePath,
            ltrim($filePath, '/'),
            'storage/'.ltrim($filePath, '/'),
            'public/'.ltrim($filePath, '/'),
        ];

        foreach ($variations as $path) {
            if (isset($this->knownPaths[$path])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format bytes to human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor] ?? 'B');
    }
}
