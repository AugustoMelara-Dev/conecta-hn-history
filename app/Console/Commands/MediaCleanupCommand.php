<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * MediaCleanupCommand - Bi-Directional Storage Reconciliation Engine
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL RISK ANALYSIS: Storage Hygiene & Race Condition Prevention ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Dual orphan sources create storage bloat:                      ║
 * ║    1. DB records with no physical file (failed uploads)                  ║
 * ║    2. Physical files with no DB record (orphaned after model deletion)   ║
 * ║                                                                          ║
 * ║  SOLUTION: Bi-Directional Reconciliation                                 ║
 * ║    Pass 1 (DB → Disk): Delete DB records without physical files          ║
 * ║    Pass 2 (Disk → DB): Delete physical files without DB records          ║
 * ║                                                                          ║
 * ║  60-MINUTE SAFETY BUFFER:                                                ║
 * ║    Files modified within the last 60 minutes are NEVER deleted.          ║
 * ║    This prevents race conditions where:                                  ║
 * ║    - User uploads file at 10:00:00                                       ║
 * ║    - Cleanup runs at 10:00:30 (before DB transaction commits)            ║
 * ║    - File would be deleted as "orphan" before it's finalized             ║
 * ║                                                                          ║
 * ║  MEMORY SAFETY: Uses chunkById(100) for DB iteration                     ║
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

    protected $description = 'Reconcile database and filesystem to remove orphaned media';

    protected int $dbOrphansDeleted = 0;
    protected int $fileOrphansDeleted = 0;
    protected int $bytesRecovered = 0;
    protected bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $bufferMinutes = (int) $this->option('buffer-minutes');
        $disk = $this->option('disk');

        $this->components->info('Media Cleanup - Bi-Directional Reconciliation');
        $this->components->bulletList([
            "Disk: {$disk}",
            "Safety buffer: {$bufferMinutes} minutes",
            "Mode: " . ($this->dryRun ? 'DRY RUN' : 'LIVE'),
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // PASS 1: Database Cleaning (DB records without physical files)
        // ═══════════════════════════════════════════════════════════════════════
        $this->cleanOrphanedDatabaseRecords($disk);

        // ═══════════════════════════════════════════════════════════════════════
        // PASS 2: Filesystem Cleaning (physical files without DB records)
        // ═══════════════════════════════════════════════════════════════════════
        $this->cleanOrphanedFiles($disk, $bufferMinutes);

        // ═══════════════════════════════════════════════════════════════════════
        // SUMMARY
        // ═══════════════════════════════════════════════════════════════════════
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
     * PASS 1: Remove database records that have no corresponding physical file.
     *
     * Uses chunkById(100) for memory-safe iteration over potentially
     * millions of media records without RAM exhaustion.
     */
    protected function cleanOrphanedDatabaseRecords(string $disk): void
    {
        $this->components->task('Pass 1: Cleaning orphaned database records', function () use ($disk) {
            // Check if Media model exists
            if (!class_exists(Media::class)) {
                $this->components->warn('Media model not found - skipping DB cleanup');
                return true;
            }

            Media::query()
                ->chunkById(100, function ($mediaRecords) use ($disk) {
                    foreach ($mediaRecords as $media) {
                        // Check if physical file exists
                        $filePath = $media->file_path ?? $media->path ?? null;
                        
                        if (!$filePath || !Storage::disk($disk)->exists($filePath)) {
                            if ($this->dryRun) {
                                $this->components->twoColumnDetail(
                                    "  Would delete DB record",
                                    "ID: {$media->id}"
                                );
                            } else {
                                // Use Eloquent delete to trigger observers
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
     * PASS 2: Remove physical files that have no corresponding database record.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  60-MINUTE SAFETY BUFFER EXPLANATION                                   ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  RACE CONDITION SCENARIO:                                              ║
     * ║                                                                        ║
     * ║  10:00:00 - User starts uploading large file                          ║
     * ║  10:00:05 - File creation begins on disk                              ║
     * ║  10:00:10 - MediaCleanupCommand starts scanning                       ║
     * ║  10:00:15 - Command finds file with no DB record (not committed yet)  ║
     * ║  10:00:20 - Command deletes "orphan" file                             ║
     * ║  10:00:25 - Upload completes, DB transaction commits                  ║
     * ║  10:00:30 - ERROR: DB record points to deleted file!                  ║
     * ║                                                                        ║
     * ║  SOLUTION: Never delete files modified within safety buffer.          ║
     * ║  Default 60 minutes accommodates slow uploads and network issues.     ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     */
    protected function cleanOrphanedFiles(string $disk, int $bufferMinutes): void
    {
        $this->components->task('Pass 2: Cleaning orphaned filesystem files', function () use ($disk, $bufferMinutes) {
            $storage = Storage::disk($disk);
            $cutoffTime = now()->subMinutes($bufferMinutes)->timestamp;

            // Get all files from the uploads directory
            $directories = ['uploads', 'media', 'products', 'tenants'];
            
            foreach ($directories as $directory) {
                if (!$storage->exists($directory)) {
                    continue;
                }

                $files = $storage->allFiles($directory);

                foreach ($files as $file) {
                    $this->processOrphanedFile($storage, $file, $cutoffTime, $disk);
                }
            }

            return true;
        });
    }

    /**
     * Process a single file for orphan detection.
     */
    protected function processOrphanedFile($storage, string $file, int $cutoffTime, string $disk): void
    {
        try {
            // Get file modification time
            $lastModified = $storage->lastModified($file);

            // ═══════════════════════════════════════════════════════════════════════
            // SAFETY BUFFER: Skip recently modified files
            // ═══════════════════════════════════════════════════════════════════════
            if ($lastModified > $cutoffTime) {
                return; // File is too recent, skip to prevent race condition
            }

            // Check if file has a corresponding database record
            $hasDbRecord = $this->fileExistsInDatabase($file);

            if (!$hasDbRecord) {
                $fileSize = $storage->size($file);

                if ($this->dryRun) {
                    $this->components->twoColumnDetail(
                        "  Would delete file",
                        "{$file} ({$this->formatBytes($fileSize)})"
                    );
                } else {
                    $storage->delete($file);
                }

                $this->fileOrphansDeleted++;
                $this->bytesRecovered += $fileSize;
            }
        } catch (\Exception $e) {
            Log::warning('MediaCleanup: Error processing file', [
                'file' => $file,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a file path exists in the database.
     */
    protected function fileExistsInDatabase(string $filePath): bool
    {
        // Check media table
        if (class_exists(Media::class)) {
            $exists = Media::query()
                ->where('file_path', $filePath)
                ->orWhere('path', $filePath)
                ->exists();
            
            if ($exists) {
                return true;
            }
        }

        // Check products table (if products have images)
        $productExists = DB::table('products')
            ->where('image', $filePath)
            ->orWhere('image', 'LIKE', "%{$filePath}%")
            ->exists();

        if ($productExists) {
            return true;
        }

        // Check tenants table (logos)
        $tenantExists = DB::table('tenants')
            ->where('logo', $filePath)
            ->orWhere('logo', 'LIKE', "%{$filePath}%")
            ->exists();

        return $tenantExists;
    }

    /**
     * Format bytes to human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor] ?? 'B');
    }
}
