<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class NormalizeTenantLegacySnapshotsCommand extends Command
{
    protected $signature = 'tenant:snapshots-normalize-legacy-plan-flag
                            {--write : Persist normalized plan_id values. Without this flag the command runs in dry-run mode.}
                            {--sample=5 : Number of aggregate UUIDs to preview}';

    protected $description = 'Normalize tenant snapshots by replacing the legacy paid-plan flag with plan_id.';

    public function __construct(
        private readonly \App\Support\Migrations\TenantLegacySnapshotStateNormalizer $normalizer
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('snapshots')) {
            $this->error('Snapshots table not found.');

            return self::FAILURE;
        }

        $write = (bool) $this->option('write');
        $sampleSize = max(1, (int) $this->option('sample'));
        $candidateSnapshots = 0;
        $normalizedSnapshots = 0;
        $invalidSnapshots = 0;
        $sampleAggregateIds = [];

        $records = DB::table('snapshots')
            ->select('aggregate_uuid', 'state')
            ->orderBy('aggregate_uuid')
            ->cursor();

        foreach ($records as $record) {
            $state = json_decode((string) $record->state, true);

            if (! is_array($state)) {
                $invalidSnapshots++;

                continue;
            }

            if (! $this->normalizer->requiresNormalization($state)) {
                continue;
            }

            $candidateSnapshots++;

            if (count($sampleAggregateIds) < $sampleSize) {
                $sampleAggregateIds[] = $record->aggregate_uuid;
            }

            if (! $write) {
                continue;
            }

            DB::table('snapshots')
                ->where('aggregate_uuid', $record->aggregate_uuid)
                ->update([
                    'state' => json_encode($this->normalizer->normalize($state), JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);

            $normalizedSnapshots++;
        }

        $mode = $write ? 'write' : 'dry-run';

        $this->line("Legacy tenant snapshot normalization complete ({$mode}).");
        $this->line("Snapshots requiring normalization: {$candidateSnapshots}");
        $this->line("Snapshots normalized: {$normalizedSnapshots}");
        $this->line("Invalid snapshot payloads: {$invalidSnapshots}");

        if ($sampleAggregateIds !== []) {
            $this->line('Sample aggregate UUIDs: '.implode(', ', $sampleAggregateIds));
        }

        if (! $write && $candidateSnapshots > 0) {
            $this->line('Re-run with --write to persist normalized snapshots.');
        }

        return self::SUCCESS;
    }
}
