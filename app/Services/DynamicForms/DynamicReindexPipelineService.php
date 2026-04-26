<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Jobs\DynamicForms\ProcessDynamicReindexChunkJob;
use App\Models\Dynamic\DynamicRecord;
use App\Models\Dynamic\DynamicReindexCheckpoint;
use App\Models\Dynamic\DynamicReindexFailure;
use App\Models\Dynamic\DynamicReindexRun;
use App\Models\Dynamic\EntityDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DynamicReindexPipelineService
{
    public function __construct(
        private readonly RecordPersistenceService $recordPersistence
    ) {}

    public function dispatchRun(EntityDefinition $entity, ?string $tenantId, int $chunkSize = 200, ?string $actorId = null): DynamicReindexRun
    {
        $chunkSize = max(10, $chunkSize);
        $total = $this->recordsQuery($entity, $tenantId)->count();

        /** @var DynamicReindexRun $run */
        $run = DynamicReindexRun::query()->create([
            'tenant_id' => $tenantId,
            'entity_definition_id' => $entity->getKey(),
            'status' => $total > 0 ? 'queued' : 'completed',
            'chunk_size' => $chunkSize,
            'total_records' => $total,
            'processed_records' => 0,
            'failed_records' => 0,
            'dispatched_by' => $actorId,
            'started_at' => $total > 0 ? null : now(),
            'finished_at' => $total > 0 ? null : now(),
        ]);

        if ($total > 0) {
            ProcessDynamicReindexChunkJob::dispatch((string) $run->getKey(), null, 1);
        }

        return $run->fresh();
    }

    public function resumeRun(DynamicReindexRun $run): DynamicReindexRun
    {
        $nextChunk = (int) ($run->checkpoints()->max('chunk_number') ?? 0) + 1;
        $cursor = $run->last_record_id ? (string) $run->last_record_id : null;

        $run->forceFill([
            'status' => 'queued',
            'finished_at' => null,
        ])->save();

        ProcessDynamicReindexChunkJob::dispatch((string) $run->getKey(), $cursor, $nextChunk);

        return $run->fresh();
    }

    public function processChunkByRunId(string $runId, ?string $cursorStart, int $chunkNumber): void
    {
        $run = DynamicReindexRun::query()->with(['entityDefinition.fields'])->find($runId);
        if (! $run instanceof DynamicReindexRun) {
            return;
        }

        if (in_array($run->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $checkpoint = DynamicReindexCheckpoint::query()->firstOrCreate(
            [
                'dynamic_reindex_run_id' => $run->getKey(),
                'chunk_number' => $chunkNumber,
            ],
            [
                'status' => 'running',
                'cursor_start' => $cursorStart,
            ]
        );

        if ($checkpoint->status === 'completed') {
            $this->recoverContinuationIfNeeded($run, $checkpoint, $chunkNumber);

            return;
        }

        $run->forceFill([
            'status' => 'running',
            'started_at' => $run->started_at ?? now(),
        ])->save();

        $checkpoint->forceFill([
            'status' => 'running',
            'cursor_start' => $cursorStart,
            'error_message' => null,
        ])->save();

        $entity = $run->entityDefinition;
        if (! $entity instanceof EntityDefinition) {
            throw new \RuntimeException('Dynamic reindex run has no valid entity definition.');
        }

        /** @var Collection<int, DynamicRecord> $records */
        $records = $this->recordsQuery($entity, $run->tenant_id ? (string) $run->tenant_id : null, $cursorStart)
            ->limit((int) $run->chunk_size)
            ->get();

        if ($records->isEmpty()) {
            $this->finalizeEmptyChunk($run, $checkpoint, $cursorStart);

            return;
        }

        $processed = 0;
        $failed = 0;
        $cursorEnd = null;

        foreach ($records as $record) {
            $cursorEnd = (string) $record->getKey();

            try {
                $this->recordPersistence->resyncIndexesForRecord($entity, $record);
                $processed++;
            } catch (\Throwable $exception) {
                $failed++;
                DynamicReindexFailure::query()->create([
                    'dynamic_reindex_run_id' => $run->getKey(),
                    'dynamic_reindex_checkpoint_id' => $checkpoint->getKey(),
                    'dynamic_record_id' => (string) $record->getKey(),
                    'chunk_number' => $chunkNumber,
                    'error_message' => $exception->getMessage(),
                    'context_json' => [
                        'tenant_id' => $run->tenant_id,
                        'entity_definition_id' => $run->entity_definition_id,
                    ],
                    'failed_at' => now(),
                ]);
            }
        }

        DB::transaction(function () use ($run, $checkpoint, $cursorEnd, $processed, $failed): void {
            /** @var DynamicReindexCheckpoint|null $lockedCheckpoint */
            $lockedCheckpoint = DynamicReindexCheckpoint::query()
                ->whereKey($checkpoint->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedCheckpoint instanceof DynamicReindexCheckpoint || $lockedCheckpoint->status === 'completed') {
                return;
            }

            $lockedCheckpoint->forceFill([
                'status' => 'completed',
                'cursor_end' => $cursorEnd,
                'processed_count' => $processed,
                'failed_count' => $failed,
                'processed_at' => now(),
                'error_message' => null,
            ])->save();

            DynamicReindexRun::query()
                ->whereKey($run->getKey())
                ->update([
                    'processed_records' => DB::raw('processed_records + '.(int) $processed),
                    'failed_records' => DB::raw('failed_records + '.(int) $failed),
                    'last_record_id' => $cursorEnd,
                    'updated_at' => now(),
                ]);
        });

        $run = $run->fresh();
        if (! $run instanceof DynamicReindexRun) {
            return;
        }

        $hasMore = $records->count() >= (int) $run->chunk_size;
        if (! $hasMore) {
            $run->forceFill([
                'status' => 'completed',
                'finished_at' => now(),
            ])->save();

            return;
        }

        ProcessDynamicReindexChunkJob::dispatch(
            runId: (string) $run->getKey(),
            cursorStart: $cursorEnd,
            chunkNumber: $chunkNumber + 1,
        );
    }

    public function markRunFailed(string $runId, int $chunkNumber, \Throwable $exception): void
    {
        $run = DynamicReindexRun::query()->find($runId);
        if (! $run instanceof DynamicReindexRun) {
            return;
        }

        $run->forceFill([
            'status' => 'failed',
            'finished_at' => now(),
        ])->save();

        DynamicReindexFailure::query()->create([
            'dynamic_reindex_run_id' => $run->getKey(),
            'dynamic_reindex_checkpoint_id' => null,
            'dynamic_record_id' => null,
            'chunk_number' => $chunkNumber,
            'error_message' => $exception->getMessage(),
            'context_json' => [
                'run_id' => $runId,
                'chunk_number' => $chunkNumber,
                'exception' => $exception::class,
            ],
            'failed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(DynamicReindexRun $run, int $checkpointLimit = 20): array
    {
        $run->loadMissing(['entityDefinition', 'checkpoints', 'failures']);
        $checkpoints = $run->checkpoints()
            ->orderByDesc('chunk_number')
            ->limit(max(1, $checkpointLimit))
            ->get()
            ->map(static fn (DynamicReindexCheckpoint $checkpoint): array => [
                'chunk_number' => (int) $checkpoint->chunk_number,
                'status' => (string) $checkpoint->status,
                'cursor_start' => $checkpoint->cursor_start,
                'cursor_end' => $checkpoint->cursor_end,
                'processed_count' => (int) $checkpoint->processed_count,
                'failed_count' => (int) $checkpoint->failed_count,
                'error_message' => $checkpoint->error_message,
                'processed_at' => optional($checkpoint->processed_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'run' => [
                'id' => (string) $run->getKey(),
                'status' => (string) $run->status,
                'tenant_id' => $run->tenant_id,
                'entity_definition_id' => (string) $run->entity_definition_id,
                'entity_key' => (string) ($run->entityDefinition?->entity_key ?? ''),
                'chunk_size' => (int) $run->chunk_size,
                'total_records' => (int) $run->total_records,
                'processed_records' => (int) $run->processed_records,
                'failed_records' => (int) $run->failed_records,
                'last_record_id' => $run->last_record_id,
                'started_at' => optional($run->started_at)?->toIso8601String(),
                'finished_at' => optional($run->finished_at)?->toIso8601String(),
                'created_at' => optional($run->created_at)?->toIso8601String(),
                'updated_at' => optional($run->updated_at)?->toIso8601String(),
            ],
            'checkpoints' => $checkpoints,
            'failure_count' => (int) $run->failures()->count(),
        ];
    }

    private function finalizeEmptyChunk(DynamicReindexRun $run, DynamicReindexCheckpoint $checkpoint, ?string $cursorStart): void
    {
        DB::transaction(function () use ($run, $checkpoint, $cursorStart): void {
            /** @var DynamicReindexCheckpoint|null $lockedCheckpoint */
            $lockedCheckpoint = DynamicReindexCheckpoint::query()
                ->whereKey($checkpoint->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedCheckpoint instanceof DynamicReindexCheckpoint) {
                return;
            }

            if ($lockedCheckpoint->status !== 'completed') {
                $lockedCheckpoint->forceFill([
                    'status' => 'completed',
                    'cursor_end' => $cursorStart,
                    'processed_count' => 0,
                    'failed_count' => 0,
                    'processed_at' => now(),
                    'error_message' => null,
                ])->save();
            }

            DynamicReindexRun::query()->whereKey($run->getKey())->update([
                'status' => 'completed',
                'started_at' => $run->started_at ?? now(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function recoverContinuationIfNeeded(DynamicReindexRun $run, DynamicReindexCheckpoint $checkpoint, int $chunkNumber): void
    {
        $run = $run->fresh();
        if (! $run instanceof DynamicReindexRun) {
            return;
        }

        $processed = (int) $run->processed_records + (int) $run->failed_records;
        if ($processed >= (int) $run->total_records) {
            if ($run->status !== 'completed') {
                $run->forceFill([
                    'status' => 'completed',
                    'finished_at' => now(),
                ])->save();
            }

            return;
        }

        $nextChunk = $chunkNumber + 1;
        $exists = DynamicReindexCheckpoint::query()
            ->where('dynamic_reindex_run_id', $run->getKey())
            ->where('chunk_number', $nextChunk)
            ->exists();

        if ($exists) {
            return;
        }

        ProcessDynamicReindexChunkJob::dispatch(
            runId: (string) $run->getKey(),
            cursorStart: $checkpoint->cursor_end ?: $run->last_record_id,
            chunkNumber: $nextChunk,
        );
    }

    private function recordsQuery(EntityDefinition $entity, ?string $tenantId, ?string $cursorStart = null)
    {
        $query = DynamicRecord::query()
            ->where('entity_definition_id', $entity->getKey())
            ->orderBy('id');

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        if ($cursorStart !== null && $cursorStart !== '') {
            $query->where('id', '>', $cursorStart);
        }

        return $query;
    }
}

