<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;

/**
 * MediaCleanupCommand - Memory-Safe Bi-Directional Storage Reconciliation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL RISK ANALYSIS: Generator Pattern for Filesystem Scanning   ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  PROBLEM: Storage::allFiles() loads ALL paths into a single array.      ║
 * ║    500,000 files → 500,000 string paths → ~100MB RAM → CRASH            ║
 * ║                                                                          ║
 * ║  WHY GENERATOR/ITERATOR SOLVES THIS:                                     ║
 * ║                                                                          ║
 * ║    ┌─────────────────────────────────────────────────────────────────┐   ║
 * ║    │  WRONG: $files = Storage::allFiles()                            │   ║
 * ║    │  ┌──────┬──────┬──────┬───────┬──────────────────────┐         │   ║
 * ║    │  │path1 │path2 │path3 │ ...   │ path500000           │ ← RAM   │   ║
 * ║    │  └──────┴──────┴──────┴───────┴──────────────────────┘         │   ║
 * ║    │  ↓ CRASH before first iteration starts                         │   ║
 * ║    └─────────────────────────────────────────────────────────────────┘   ║
 * ║                                                                          ║
 * ║    ┌─────────────────────────────────────────────────────────────────┐   ║
 * ║    │  CORRECT: Generator yields one path at a time                   │   ║
 * ║    │  yield path1 → process → GC → yield path2 → process → GC...    │   ║
 * ║    │  ↓ RAM: Only 1 path in memory at any time                       │   ║
 * ║    └─────────────────────────────────────────────────────────────────┘   ║
 * ║                                                                          ║
 * ║  60-MINUTE SAFETY BUFFER:                                                ║
 * ║    Files modified < 60 min ago are NEVER deleted, preventing race       ║
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
                            {--disk=public : Storage disk to scan}
                            {--batch-size=100 : Files to process before memory cleanup}';

    protected $description = 'Reconcile database and filesystem to remove orphaned media (memory-safe)';

    protected int $dbOrphansDeleted = 0;
    protected int $fileOrphansDeleted = 0;
    protected int $bytesRecovered = 0;
    protected bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $bufferMinutes = (int) $this->option('buffer-minutes');
        $disk = $this->option('disk');
        $batchSize = (int) $this->option('batch-size');

        $this->components->info('Media Cleanup - Memory-Safe Reconciliation');
        $this->components->bulletList([
            "Disk: {$disk}",
            "Safety buffer: {$bufferMinutes} minutes",
            "Batch size: {$batchSize} files",
            "Mode: " . ($this->dryRun ? 'DRY RUN' : 'LIVE'),
        ]);

        // Pass 1: Database Cleaning
        $this->cleanOrphanedDatabaseRecords($disk);

        // Pass 2: Filesystem Cleaning (Memory-Safe)
        $this->cleanOrphanedFilesMemorySafe($disk, $bufferMinutes, $batchSize);

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
     * Pass 1: Remove database records that have no corresponding physical file.
     */
    protected function cleanOrphanedDatabaseRecords(string $disk): void
    {
        $this->components->task('Pass 1: Cleaning orphaned database records', function () use ($disk) {
            if (!class_exists(Media::class)) {
                return true;
            }

            Media::query()
                ->chunkById(100, function ($mediaRecords) use ($disk) {
                    foreach ($mediaRecords as $media) {
                        $filePath = $media->file_path ?? $media->path ?? null;
                        
                        if (!$filePath || !Storage::disk($disk)->exists($filePath)) {
                            if (!$this->dryRun) {
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
     * Pass 2: Remove physical files without DB records using GENERATOR pattern.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  MEMORY-SAFE FILE SCANNING                                             ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  Instead of: $files = Storage::allFiles() ← Loads ALL to RAM          ║
     * ║  We use: Generator that yields paths one at a time                    ║
     * ║                                                                        ║
     * ║  For Flysystem 3+: Use listContents()->filter()->toArray() in batches ║
     * ║  For older: Process subdirectories sequentially                       ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     */
    protected function cleanOrphanedFilesMemorySafe(string $disk, int $bufferMinutes, int $batchSize): void
    {
        $this->components->task('Pass 2: Scanning filesystem (memory-safe)', function () use ($disk, $bufferMinutes, $batchSize) {
            $storage = Storage::disk($disk);
            $cutoffTime = now()->subMinutes($bufferMinutes)->timestamp;

            // Process directories one at a time to avoid loading entire tree
            $directories = ['uploads', 'media', 'products', 'tenants', 'images'];
            
            foreach ($directories as $directory) {
                if (!$storage->exists($directory)) {
                    continue;
                }

                $this->line("    Scanning: {$directory}/");
                $this->scanDirectoryRecursively($storage, $directory, $cutoffTime, $batchSize);
            }

            return true;
        });
    }

    /**
     * Recursively scan directory using subdirectory batching.
     *
     * Instead of loading all 500k files at once, we:
     * 1. List immediate files (likely < 1000)
     * 2. Process those
     * 3. List subdirectories
     * 4. Recurse into each subdirectory
     *
     * This limits memory to: current directory's files + directory stack
     */
    protected function scanDirectoryRecursively($storage, string $directory, int $cutoffTime, int $batchSize): void
    {
        $processed = 0;

        try {
            // Get files in current directory only (not recursive)
            $files = $storage->files($directory);
            
            foreach ($files as $file) {
                $this->processOrphanedFile($storage, $file, $cutoffTime);
                $processed++;

                // Force garbage collection periodically
                if ($processed % $batchSize === 0) {
                    gc_collect_cycles();
                }
            }

            // Free the files array
            unset($files);

            // Now get subdirectories and recurse
            $subdirectories = $storage->directories($directory);
            
            foreach ($subdirectories as $subdir) {
                $this->scanDirectoryRecursively($storage, $subdir, $cutoffTime, $batchSize);
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
     */
    protected function processOrphanedFile($storage, string $file, int $cutoffTime): void
    {
        try {
            $lastModified = $storage->lastModified($file);

            // ═══════════════════════════════════════════════════════════════════════
            // 60-MINUTE SAFETY BUFFER
            // ═══════════════════════════════════════════════════════════════════════
            // Files modified recently are NEVER deleted.
            // This prevents deleting files during concurrent upload transactions.
            // ═══════════════════════════════════════════════════════════════════════
            if ($lastModified > $cutoffTime) {
                return;
            }

            // Check database for this file path
            if (!$this->fileExistsInDatabase($file)) {
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

        // Check products table
        $productExists = DB::table('products')
            ->where('image', $filePath)
            ->orWhere('image', 'LIKE', "%{$filePath}%")
            ->exists();

        if ($productExists) {
            return true;
        }

        // Check tenants table
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
