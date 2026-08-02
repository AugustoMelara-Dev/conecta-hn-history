<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\BusinessGraph\BusinessGraphBackfill;
use App\Infrastructure\BusinessGraph\DatabaseBusinessGraphRepository;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class BackfillBusinessGraphCommand extends Command
{
    protected $signature = 'dovra:backfill-business-graph {--tenant= : Limit the backfill to one tenant UUID} {--dry-run : Report intended writes without mutating graph tables}';

    protected $description = 'Backfill the tenant-scoped Dovra Business Graph from canonical business data.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = is_string($tenantId) && trim($tenantId) !== '' ? $tenantId : null;

        try {
            $report = (new BusinessGraphBackfill(new DatabaseBusinessGraphRepository))->run(
                tenantId: $tenantId,
                dryRun: (bool) $this->option('dry-run'),
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('dry_run: '.($report->dryRun ? 'true' : 'false'));
        $this->line('tenant_id: '.($report->tenantId ?? 'all'));
        $this->line('facts_written: '.$report->factsWritten);
        $this->line('edges_written: '.$report->edgesWritten);
        $this->line('source_type_counts: '.json_encode($report->sourceTypeCounts, JSON_THROW_ON_ERROR));

        if ($report->skippedSources !== []) {
            $this->line('skipped_sources: '.json_encode($report->skippedSources, JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }
}
