<?php

declare(strict_types=1);

namespace App\Jobs\DynamicForms;

use App\Services\DynamicForms\DynamicReindexPipelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessDynamicReindexChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 90, 180, 300];

    public function __construct(
        public readonly string $runId,
        public readonly ?string $cursorStart,
        public readonly int $chunkNumber,
    ) {}

    public function handle(DynamicReindexPipelineService $pipeline): void
    {
        $pipeline->processChunkByRunId(
            runId: $this->runId,
            cursorStart: $this->cursorStart,
            chunkNumber: max(1, $this->chunkNumber),
        );
    }

    public function failed(\Throwable $exception): void
    {
        app(DynamicReindexPipelineService::class)->markRunFailed(
            runId: $this->runId,
            chunkNumber: max(1, $this->chunkNumber),
            exception: $exception,
        );
    }
}

