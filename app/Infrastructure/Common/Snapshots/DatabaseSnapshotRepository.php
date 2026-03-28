<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Snapshots;

use App\Domain\Common\Contracts\SnapshotRepositoryInterface;
use App\Domain\Common\ValueObjects\Snapshot;
use Illuminate\Support\Facades\DB;

/**
 * DatabaseSnapshotRepository - High Efficiency State Loader
 */
final readonly class DatabaseSnapshotRepository implements SnapshotRepositoryInterface
{
    public function save(string $aggregateType, string $aggregateId, int $version, array $state): void
    {
        DB::table('snapshots')->upsert(
            [
                'aggregate_uuid' => $aggregateId,
                'aggregate_type' => $aggregateType,
                'aggregate_version' => $version,
                'state' => json_encode($state),
                'created_at' => now(),
            ],
            ['aggregate_uuid'], // Unique Key
            ['aggregate_version', 'state', 'created_at'] // Update Columns
        );
    }

    public function get(string $aggregateId): ?Snapshot
    {
        $record = DB::table('snapshots')->where('aggregate_uuid', $aggregateId)->first();

        if (! $record) {
            return null;
        }

        return new Snapshot(
            state: json_decode($record->state, true),
            version: $record->aggregate_version
        );
    }
}
