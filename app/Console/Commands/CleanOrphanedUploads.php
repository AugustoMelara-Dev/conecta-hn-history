<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * CleanOrphanedUploads - Storage Garbage Collector
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ORPHAN FILE DETECTION: Safety Net for Upload Interruptions             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * PROBLEM SCENARIO:
 * If a PHP process crashes or request is interrupted AFTER uploading a file
 * but BEFORE the database record is created, the file becomes orphaned.
 * This is extremely rare but can accumulate over time (storage rot).
 *
 * SOLUTION:
 * This command scans tenant storage directories and identifies files that:
 * 1. Have no corresponding database record (image_path match)
 * 2. Are older than a configurable grace period (default: 1 hour)
 *
 * SAFETY:
 * The grace period ensures we don't delete files that are still being processed
 * in an active request (compensating for slow uploads or transactions).
 *
 * RECOMMENDED SCHEDULE: Hourly via Laravel Scheduler
 *   $schedule->command('storage:cleanup-orphans')->hourly();
 */
class CleanOrphanedUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:cleanup-orphans 
                            {--dry-run : List orphans without deleting}
                            {--grace=60 : Grace period in minutes before considering file orphaned}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned upload files that have no database record';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $graceMinutes = (int) $this->option('grace');
        $graceThreshold = now()->subMinutes($graceMinutes)->timestamp;

        $this->info('🔍 Scanning for orphaned files...');
        $this->info("   Grace period: {$graceMinutes} minutes");
        $this->info('   Mode: '.($dryRun ? 'DRY RUN (no deletions)' : 'LIVE'));
        $this->newLine();

        $disk = Storage::disk('public');
        $orphanedFiles = [];
        $totalSize = 0;

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: Scan tenant directories for product images
        // ═══════════════════════════════════════════════════════════════════════
        $tenantDirs = $disk->directories('tenants');

        foreach ($tenantDirs as $tenantDir) {
            $productDir = $tenantDir.'/products';

            if (! $disk->exists($productDir)) {
                continue;
            }

            $files = $disk->allFiles($productDir);

            foreach ($files as $file) {
                // Check if file is older than grace period
                $lastModified = $disk->lastModified($file);

                if ($lastModified > $graceThreshold) {
                    // File is too new - might still be in active transaction
                    continue;
                }

                // Check if file has a database record
                $hasRecord = Product::withTrashed()
                    ->where('image_path', $file)
                    ->exists();

                if (! $hasRecord) {
                    $orphanedFiles[] = $file;
                    $totalSize += $disk->size($file);
                }
            }
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: Report findings
        // ═══════════════════════════════════════════════════════════════════════
        if (empty($orphanedFiles)) {
            $this->info('✅ No orphaned files found. Storage is clean!');

            return self::SUCCESS;
        }

        $this->warn('⚠️  Found '.count($orphanedFiles).' orphaned file(s)');
        $this->info('   Total size: '.$this->formatBytes($totalSize));
        $this->newLine();

        // Display table of orphaned files
        $tableData = array_map(function ($file) use ($disk) {
            return [
                'file' => $file,
                'size' => $this->formatBytes($disk->size($file)),
                'age' => now()->diffForHumans(
                    \Carbon\Carbon::createFromTimestamp($disk->lastModified($file))
                ),
            ];
        }, $orphanedFiles);

        $this->table(['File Path', 'Size', 'Age'], $tableData);

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 3: Delete or report
        // ═══════════════════════════════════════════════════════════════════════
        if ($dryRun) {
            $this->newLine();
            $this->info('🔒 Dry run complete. No files were deleted.');
            $this->info('   Run without --dry-run to delete orphaned files.');

            return self::SUCCESS;
        }

        // Proceed with deletion
        $this->newLine();
        if (! $this->confirm('Delete these orphaned files?', true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $failed = 0;

        foreach ($orphanedFiles as $file) {
            try {
                $disk->delete($file);
                $deleted++;

                Log::info('Orphaned file deleted', [
                    'file' => $file,
                    'command' => 'storage:cleanup-orphans',
                ]);
            } catch (\Exception $e) {
                $failed++;
                $this->error("Failed to delete: {$file}");

                Log::error('Failed to delete orphaned file', [
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('✅ Cleanup complete!');
        $this->info("   Deleted: {$deleted} file(s)");

        if ($failed > 0) {
            $this->warn("   Failed: {$failed} file(s)");
        }

        $this->info('   Freed: '.$this->formatBytes($totalSize));

        return self::SUCCESS;
    }

    /**
     * Format bytes to human-readable size.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
