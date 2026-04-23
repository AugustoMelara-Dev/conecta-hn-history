<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class DirectoryGuidePreflightCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'directory:guide:preflight
        {--strict : Exit with code 1 when required checks fail}
        {--json : Emit JSON output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate required runtime configuration for directory guide hybrid operations.';

    public function handle(): int
    {
        $checks = [
            'APP_KEY' => $this->checkAppKey(),
            'OPENAI_API_KEY' => $this->checkNotBlank((string) config('services.openai.api_key', '')),
            'PLATFORM_APP_URL' => $this->checkUrl((string) config('app.platform_app_url', '')),
            'PLATFORM_DIRECTORY_URL' => $this->checkUrl((string) config('app.platform_directory_url', '')),
            'DIRECTORY_GUIDE_EMBEDDING_MODEL' => $this->checkNotBlank((string) config('directory.guide.embedding_model', '')),
            'DIRECTORY_GUIDE_MAX_RERANK_CANDIDATES' => (int) config('directory.guide.max_rerank_candidates', 0) >= 1,
            'PGVECTOR_TABLE' => $this->checkNotBlank((string) config('directory.guide.pgvector.table', '')),
            'PGVECTOR_DIMENSION' => max(1, (int) config('directory.guide.pgvector.dimension', 0)) >= 512,
            'directory_product_embeddings_table' => Schema::hasTable((string) config('directory.guide.pgvector.table', 'directory_product_embeddings')),
            'QDRANT_ENABLED_FLAG' => is_bool(config('directory.guide.qdrant.enabled')),
            'QDRANT_COLLECTION' => $this->checkNotBlank((string) config('directory.guide.qdrant.collection', '')),
        ];

        if ((bool) config('directory.guide.qdrant.enabled', false)) {
            $checks['QDRANT_URL'] = $this->checkUrl((string) config('directory.guide.qdrant.url', ''));
        }

        $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => ! $ok));
        $summary = [
            'status' => $failed === [] ? 'ok' : 'failed',
            'failed_checks' => $failed,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $name => $ok) {
                $this->line(sprintf('%s %s', $ok ? 'OK   ' : 'FAIL ', $name));
            }
        }

        if ($failed !== [] && (bool) $this->option('strict')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function checkAppKey(): bool
    {
        $appKey = (string) config('app.key', '');

        if ($appKey === '') {
            return false;
        }

        return str_starts_with($appKey, 'base64:') || strlen($appKey) >= 32;
    }

    private function checkNotBlank(string $value): bool
    {
        return trim($value) !== '';
    }

    private function checkUrl(string $value): bool
    {
        if (trim($value) === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
