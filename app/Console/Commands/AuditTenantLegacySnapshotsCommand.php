<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AuditTenantLegacySnapshotsCommand extends Command
{
    protected $signature = 'tenant:snapshots-audit {--sample=5 : Number of aggregate UUIDs to preview}';

    protected $description = 'Audit tenant snapshots that still depend on the legacy paid-plan flag.';

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

        $totalSnapshots = 0;
        $tenantSnapshots = 0;
        $legacyTenantSnapshots = 0;
        $snapshotsRequiringNormalization = 0;
        $invalidSnapshots = 0;
        $sampleAggregateIds = [];
        $sampleSize = max(1, (int) $this->option('sample'));

        $records = DB::table('snapshots')
            ->select('aggregate_uuid', 'state')
            ->orderBy('aggregate_uuid')
            ->cursor();

        foreach ($records as $record) {
            $totalSnapshots++;

            $state = json_decode((string) $record->state, true);

            if (! is_array($state)) {
                $invalidSnapshots++;

                continue;
            }

            if (! $this->normalizer->looksLikeTenantSnapshot($state)) {
                continue;
            }

            $tenantSnapshots++;

            if (! $this->normalizer->hasLegacyPaidPlanFlag($state)) {
                continue;
            }

            $legacyTenantSnapshots++;

            if (! $this->normalizer->requiresNormalization($state)) {
                continue;
            }

            $snapshotsRequiringNormalization++;

            if (count($sampleAggregateIds) < $sampleSize) {
                $sampleAggregateIds[] = $record->aggregate_uuid;
            }
        }

        $this->line('Snapshot audit complete.');
        $this->line("Total snapshots: {$totalSnapshots}");
        $this->line("Tenant-like snapshots: {$tenantSnapshots}");
        $this->line("Tenant snapshots with legacy paid-plan flag: {$legacyTenantSnapshots}");
        $this->line("Tenant snapshots requiring normalization: {$snapshotsRequiringNormalization}");
        $this->line("Invalid snapshot payloads: {$invalidSnapshots}");

        if ($sampleAggregateIds !== []) {
            $this->line('Sample aggregate UUIDs requiring normalization: '.implode(', ', $sampleAggregateIds));
        }

        if ($snapshotsRequiringNormalization > 0) {
            $this->line('Next step: php artisan tenant:snapshots-normalize-legacy-plan-flag --write');
        }

        return self::SUCCESS;
    }
}
