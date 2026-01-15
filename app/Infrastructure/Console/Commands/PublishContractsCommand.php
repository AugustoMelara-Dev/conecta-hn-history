<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;

class PublishContractsCommand extends Command
{
    protected $signature = 'schema:publish {--target=public/docs/schemas : Target directory for publishing}';
    protected $description = 'Publish validated schemas to the Developer Portal artifact registry';

    public function handle(): int
    {
        $sourcePath = resource_path('schemas');
        $targetPath = base_path($this->option('target'));

        if (!is_dir($sourcePath)) {
            $this->error("Source directory [{$sourcePath}] does not exist.");
            return Command::FAILURE;
        }

        if (!is_dir($targetPath)) {
            if (!mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
                $this->error("Failed to create target directory [{$targetPath}].");
                return Command::FAILURE;
            }
        }

        $files = glob($sourcePath . '/*.json');
        $this->info("Publishing " . count($files) . " schemas to [{$targetPath}]...");

        $gitHash = trim(exec('git rev-parse --short HEAD') ?? 'unknown');
        $now = date('c');

        foreach ($files as $file) {
            $filename = basename($file);
            $content = json_decode(file_get_contents($file), true);

            // Enrichment: Adding Metadata Envelope
            $content['_meta'] = [
                'published_at' => $now,
                'git_hash' => $gitHash,
                'version_hash' => md5(json_encode($content)), // Content-addressable versioning
                'governance_level' => 'Titanium'
            ];

            $destination = $targetPath . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($destination, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            
            $this->line("  - Published: {$filename}");
        }

        $this->info('Contracts successfully published to Developer Portal.');
        return Command::SUCCESS;
    }
}
