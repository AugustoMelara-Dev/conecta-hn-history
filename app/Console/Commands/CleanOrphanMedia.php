<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOrphanMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:clean-orphans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphan media files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning orphan media...');

        // If using spatie/laravel-medialibrary
        if (class_exists(\Spatie\MediaLibrary\MediaLibraryServiceProvider::class)) {
            $this->call('media-library:clean');
        } else {
            // Fallback logic for temporary uploads
            $this->info('Spatie Media Library not found. Cleaning temporary files...');
            
            // Clean livewire-tmp if it exists (older than 24h)
            $tmpPath = 'livewire-tmp';
            if (Storage::exists($tmpPath)) {
                $files = Storage::files($tmpPath);
                $count = 0;
                foreach ($files as $file) {
                    if (Storage::lastModified($file) < now()->subDay()->getTimestamp()) {
                        Storage::delete($file);
                        $count++;
                    }
                }
                $this->info("{$count} temporary files cleaned.");
            } else {
                $this->info('No temporary directory found.');
            }
        }

        $this->info('Orphan media cleaning completed.');
    }
}
