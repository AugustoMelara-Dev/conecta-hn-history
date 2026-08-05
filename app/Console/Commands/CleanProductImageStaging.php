<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanProductImageStaging extends Command
{
    protected $signature = 'storage:cleanup-product-staging
                            {--dry-run : List stale staging objects without deleting}
                            {--grace=60 : Grace period in minutes}';

    protected $description = 'Delete stale private merchant product-image staging objects';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $threshold = now()->subMinutes(max(-1, (int) $this->option('grace')))->timestamp;
        $stale = [];

        foreach ($disk->allFiles('manual-product-staging') as $path) {
            if (! str_starts_with($path, 'manual-product-staging/')) {
                continue;
            }

            if ($disk->lastModified($path) <= $threshold) {
                $stale[] = $path;
            }
        }

        if ((bool) $this->option('dry-run')) {
            foreach ($stale as $path) {
                $this->line($path);
            }

            $this->info(count($stale).' stale staging object(s) found.');

            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($stale as $path) {
            if (! $disk->delete($path)) {
                $failures++;
            }
        }

        if ($failures > 0) {
            $this->error("{$failures} stale staging object(s) could not be deleted.");

            return self::FAILURE;
        }

        $this->info(count($stale).' stale staging object(s) deleted.');

        return self::SUCCESS;
    }
}
